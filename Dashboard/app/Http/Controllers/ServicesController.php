<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

class ServicesController extends Controller
{
    /**
     * Whitelist of systemd units this page is allowed to see/control.
     * NEVER accept a raw service name from the request — always resolve
     * through this map so an attacker can't pass e.g. "sshd --stop" or
     * some other unit and get it touched.
     *
     * key   = systemd unit name (must match `systemctl list-units`)
     * value = friendly label shown in the UI
     */
    public const MANAGED_SERVICES = [
        'ems-air-quality.service' => 'EMS Air Quality',
        'ems-seismic.service'     => 'EMS Seismic',
        'ems-api.service'         => 'EMS API',
        'mosquitto.service'       => 'Mosquitto (MQTT Broker)',
        'postgresql.service'      => 'PostgreSQL',
        'nginx.service'           => 'Nginx',
        'ntpsec.service'          => 'NTP Daemon (ntpsec)',
        'sms.service'          => 'SMS', 
    ];

    /** Actions allowed via the control buttons. */
    private const ALLOWED_ACTIONS = ['start', 'stop', 'restart'];
    private const SMS_ALLOWED_ACTIONS = ['enable', 'disable'];

    /**
     * Whitelist of config files that can be viewed/edited from the UI,
     * keyed by the managed service they belong to. Same rule as
     * MANAGED_SERVICES: the path NEVER comes from the request — only
     * from this map, so a user can never read/write an arbitrary file.
     */
    public const CONFIG_FILES = [
        'nginx.service' => '/etc/nginx/sites-enabled/ems-dashboard',
    ];

    public function index()
    {
        return view('server.services', [
            'services' => $this->collectStatuses(),
        ]);
    }

    /**
     * Standalone terminal page — no service cards, just the xterm.js
     * session. Kept separate so it can be opened in its own tab/window
     * without re-establishing the status-polling loop.
     */
    public function terminal()
    {
        return view('server.terminal');
    }

    /**
     * AJAX polling endpoint — returns fresh status for every managed service.
     * GET /maintenance/services/status
     */
    public function status(): JsonResponse
    {
        return response()->json($this->collectStatuses());
    }

    /**
     * POST /maintenance/services/{service}/action  { action: start|stop|restart }
     *
     * $service arrives from the route as the raw string the user's browser
     * sent — it is NEVER trusted until it's confirmed to be a key in
     * MANAGED_SERVICES below.
     */
    public function action(Request $request, string $service): JsonResponse
    {
        $validated = $request->validate([
            'action' => 'required|string|in:' . implode(',', self::ALLOWED_ACTIONS),
        ]);

        if (!array_key_exists($service, self::MANAGED_SERVICES)) {
            return response()->json(['message' => 'Unknown or unmanaged service.'], 422);
        }

        $action = $validated['action'];

        // Define .env file path (adjust if needed)
        $envPath = '/home/system/EMS/Dashboard/.env'; // Or use base_path('.env') if using Laravel's path

        // If SMS service, also update .env file
        if ($service === 'sms.service') {
            if (!file_exists($envPath)) {
                return response()->json(['message' => '.env file not found.'], 500);
            }
            
            // Read current .env content
            $envContent = file_get_contents($envPath);
            
            // Set the new value
            $newValue = $action === 'enable' ? 'true' : 'false';
            $envContent = preg_replace(
                '/^SMS_INGESTION_ENABLED=.*$/m',
                'SMS_INGESTION_ENABLED=' . $newValue,
                $envContent
            );
            
            // Write back to .env
            if (file_put_contents($envPath, $envContent) === false) {
                return response()->json([
                    'message' => 'Failed to update .env file.',
                ], 500);
            }
        }

        // Symfony Process with an array of args — no shell string is ever
        // built, so there is no injection surface here regardless of what
        // $service/$action contain (and they're whitelisted anyway).
        $process = new Process(['sudo', '/usr/bin/systemctl', $action, $service]);
        $process->setTimeout(20);
        $process->run();

        $success = $process->isSuccessful();

        Log::channel('services')->info('Service action', [
            'user'    => Auth::user()->username ?? session('username'),
            'service' => $service,
            'action'  => $action,
            'success' => $success,
            'output'  => trim($process->getOutput() . $process->getErrorOutput()),
        ]);

        if (!$success) {
            return response()->json([
                'message' => "Failed to {$action} {$service}.",
                'detail'  => trim($process->getErrorOutput()) ?: trim($process->getOutput()),
            ], 500);
        }

        return response()->json([
            'message' => ucfirst($action) . " sent to {$service}.",
            'service' => $this->statusFor($service),
        ]);
    }

    /**
     * GET /maintenance/services/{service}/config
     * Returns the raw contents of the whitelisted config file for $service.
     */
    public function configShow(string $service): JsonResponse
    {
        if (!array_key_exists($service, self::CONFIG_FILES)) {
            return response()->json(['message' => 'No editable config for this service.'], 422);
        }

        $path = self::CONFIG_FILES[$service];

        if (!is_readable($path)) {
            return response()->json(['message' => "Config file is not readable: {$path}"], 500);
        }

        return response()->json([
            'service' => $service,
            'path'    => $path,
            'content' => file_get_contents($path),
        ]);
    }

    /**
     * POST /maintenance/services/{service}/config  { content: string }
     *
     * Flow: backup current file -> write new content -> `nginx -t`.
     *   - Test fails  -> restore the backup, return 422 with the nginx
     *                    error output. Nothing is left changed on disk,
     *                    and the modal stays open on the client so the
     *                    user can fix the text and retry.
     *   - Test passes -> drop the backup, restart the service, return
     *                    the fresh status.
     */
    public function configUpdate(Request $request, string $service): JsonResponse
    {
        $validated = $request->validate([
            'content' => 'required|string',
        ]);

        if (!array_key_exists($service, self::CONFIG_FILES)) {
            return response()->json(['message' => 'No editable config for this service.'], 422);
        }

        $path = self::CONFIG_FILES[$service];
        $user = Auth::user()->username ?? session('username');

        $tmpPath = tempnam(sys_get_temp_dir(), 'cfg_');
        file_put_contents($tmpPath, $validated['content']);

        // IMPORTANT: the backup must NOT live inside /etc/nginx/sites-enabled/
        // (or any other nginx-included directory). Debian/Ubuntu's nginx.conf
        // does `include /etc/nginx/sites-enabled/*;` with no extension filter,
        // so a backup dropped next to the real file gets parsed as its own
        // server block too — that's what caused the earlier "duplicate
        // default server" failure. Keeping it in the system temp dir means
        // nginx never sees it.
        $backupPath = tempnam(sys_get_temp_dir(), 'nginxcfg_backup_');
        $backup = new Process(['sudo', '/bin/cp', '--preserve=mode,ownership', $path, $backupPath]);
        $backup->run();

        if (!$backup->isSuccessful()) {
            @unlink($tmpPath);
            @unlink($backupPath);
            return response()->json([
                'message' => 'Could not back up the existing config; aborted before making changes.',
                'detail'  => trim($backup->getErrorOutput()),
            ], 500);
        }

        $deploy = new Process(['sudo', '/bin/cp', $tmpPath, $path]);
        $deploy->run();
        @unlink($tmpPath);

        if (!$deploy->isSuccessful()) {
            return response()->json([
                'message' => 'Failed to write the config file.',
                'detail'  => trim($deploy->getErrorOutput()),
            ], 500);
        }

        $test = new Process(['sudo', '/usr/sbin/nginx', '-t']);
        $test->setTimeout(15);
        $test->run();

        if (!$test->isSuccessful()) {
            $restore = new Process(['sudo', '/bin/cp', $backupPath, $path]);
            $restore->run();

            Log::channel('services')->warning('Nginx config test failed — rolled back', [
                'user'    => $user,
                'service' => $service,
                'detail'  => trim($test->getErrorOutput() . $test->getOutput()),
            ]);

            return response()->json([
                'message' => 'nginx -t failed. Your changes were NOT applied and the previous config is still active.',
                'detail'  => trim($test->getErrorOutput() . $test->getOutput()),
            ], 422);
        }

        $restart = new Process(['sudo', '/usr/bin/systemctl', 'restart', $service]);
        $restart->setTimeout(20);
        $restart->run();

        $cleanup = new Process(['sudo', '/bin/rm', '-f', $backupPath]);
        $cleanup->run();

        Log::channel('services')->info('Nginx config updated', [
            'user'            => $user,
            'service'         => $service,
            'restart_success' => $restart->isSuccessful(),
        ]);

        if (!$restart->isSuccessful()) {
            return response()->json([
                'message' => 'Config passed nginx -t and was saved, but the restart failed.',
                'detail'  => trim($restart->getErrorOutput()),
            ], 500);
        }

        return response()->json([
            'message' => 'Config saved, passed nginx -t, and nginx was restarted.',
            'service' => $this->statusFor($service),
        ]);
    }

    /**
     * @return array<int, array{unit:string,label:string,active:string,enabled:string,running:bool,hasConfig:bool}>
     */
    private function collectStatuses(): array
    {
        $out = [];
        foreach (self::MANAGED_SERVICES as $unit => $label) {
            $out[] = $this->statusFor($unit);
        }
        return $out;
    }

    private function statusFor(string $unit): array
    {
        $label = self::MANAGED_SERVICES[$unit] ?? $unit;

        $active = $this->runQuiet(['systemctl', 'is-active', $unit]);   // active | inactive | failed | activating...
        $enabled = $this->runQuiet(['systemctl', 'is-enabled', $unit]); // enabled | disabled | static...

        return [
            'unit'      => $unit,
            'label'     => $label,
            'active'    => $active ?: 'unknown',
            'enabled'   => $enabled ?: 'unknown',
            'running'   => $active === 'active',
            'hasConfig' => array_key_exists($unit, self::CONFIG_FILES),
            'isSms' => $unit === 'sms.service',
        ];
    }

    private function runQuiet(array $command): string
    {
        $process = new Process($command);
        $process->setTimeout(5);
        // is-active / is-enabled exit non-zero for inactive/disabled units —
        // that's expected, we still want their stdout.
        $process->run();
        return trim($process->getOutput());
    }
}