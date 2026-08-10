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
    ];

    /** Actions allowed via the control buttons. */
    private const ALLOWED_ACTIONS = ['start', 'stop', 'restart'];

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
     * @return array<int, array{unit:string,label:string,active:string,enabled:string,running:bool}>
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
            'unit'    => $unit,
            'label'   => $label,
            'active'  => $active ?: 'unknown',
            'enabled' => $enabled ?: 'unknown',
            'running' => $active === 'active',
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