<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class MaintenanceController extends Controller
{
    // ------------------------------------------------------------------------
    // nmcli device detection
    //
    // Duplicated (deliberately, in miniature) from NetworkController rather
    // than shared, since this page only needs device *names* — not the
    // connection-profile resolution NetworkController also does. If a third
    // page ever needs the same list, pull detectDevices()/detectEthDevices()
    // (and runNmcli/isSudoAuthFailure) into a shared trait at that point.
    // ------------------------------------------------------------------------

    private function runNmcli(string $command): string
    {
        $fullCmd = 'sudo -n ' . $command . ' 2>&1';
        Log::debug("Executing: {$fullCmd}");
        $output = shell_exec($fullCmd);
        Log::debug("Output: " . ($output ?: '(empty)'));
        $output = $output ?? '';

        if ($this->isSudoAuthFailure($output)) {
            $whoami = trim((string) shell_exec('whoami 2>&1'));
            $msg = "sudo requires a password for user '{$whoami}' when running nmcli. "
                 . "Add a NOPASSWD rule for this user and the nmcli binary in /etc/sudoers.d/, "
                 . "e.g.: {$whoami} ALL=(ALL) NOPASSWD: /usr/bin/nmcli. Raw output: {$output}";
            Log::error($msg);
            throw new \Exception($msg);
        }

        return $output;
    }

    private function isSudoAuthFailure(string $output): bool
    {
        return (bool) preg_match(
            '/a password is required|no tty present|askpass|sudo:.*password/i',
            $output
        );
    }

    /**
     * Device names of every nmcli device of the given TYPE, in the order
     * nmcli reports them. Returns an empty array — never throws — when no
     * device of this type is present.
     */
    private function detectDevices(string $type): array
    {
        $output = $this->runNmcli('nmcli -t -f DEVICE,TYPE,STATE device status');
        if (preg_match('/error|failed/i', $output)) {
            throw new \Exception("Failed to get device status: {$output}");
        }

        $devices = [];
        foreach (explode("\n", trim($output)) as $line) {
            if (empty($line)) continue;
            $parts = explode(':', $line);
            if (count($parts) < 3) continue;
            [$device, $devType] = [trim($parts[0]), trim($parts[1])];
            if ($devType === $type) {
                $devices[] = $device;
            }
        }

        return $devices;
    }

    private function detectEthDevices(): array
    {
        return $this->detectDevices('ethernet');
    }

    /**
     * Restrict a user-supplied host to characters valid in a hostname or
     * IPv4/IPv6 address. escapeshellarg() already prevents shell breakout,
     * but this additionally stops something that *looks* like a command
     * flag (e.g. "-oProxyCommand=...") from being smuggled in as the host,
     * since ping/traceroute would otherwise happily parse it as an option.
     */
    private function sanitizeHost(string $host): ?string
    {
        $host = trim($host);
        if ($host === '' || strlen($host) > 253) {
            return null;
        }
        if ($host[0] === '-') {
            return null;
        }
        if (!preg_match('/^[a-zA-Z0-9.:_-]+$/', $host)) {
            return null;
        }
        return $host;
    }

    /**
     * Confirm $device is one of the Ethernet devices nmcli currently
     * reports, so ping/traceroute can only ever be bound to a real,
     * present interface — never an arbitrary string from the request.
     * Throws InvalidArgumentException (mapped to a 422) rather than the
     * generic \Exception the nmcli helpers use (mapped to a 500), since
     * an unknown device here is a bad request, not a server failure.
     */
    private function assertKnownEthDevice(string $device): void
    {
        if (!in_array($device, $this->detectEthDevices(), true)) {
            throw new \InvalidArgumentException("'{$device}' is not a recognized Ethernet interface on this system.");
        }
    }

    // ------------------------------------------------------------------------
    // Page + interface list
    // ------------------------------------------------------------------------

    public function index()
    {
        return view('server.maintenance');
    }

    public function interfaces()
    {
        try {
            $devices = $this->detectEthDevices();
            return response()->json(['success' => true, 'devices' => $devices]);
        } catch (\Exception $e) {
            Log::error('Maintenance interfaces error: ' . $e->getMessage());
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    // ------------------------------------------------------------------------
    // Diagnostics
    // ------------------------------------------------------------------------

    public function ping(Request $request)
    {
        try {
            $validated = $request->validate([
                'device' => 'required|string',
                'host'   => 'required|string|max:253',
                'count'  => 'nullable|integer|min:1|max:20',
            ]);

            $this->assertKnownEthDevice($validated['device']);

            $host = $this->sanitizeHost($validated['host']);
            if ($host === null) {
                return response()->json(['success' => false, 'error' => 'Invalid hostname or IP address.'], 422);
            }

            $count = $validated['count'] ?? 4;
            $device = $validated['device'];

            // -4: IPv4 only, for now (this page's inputs assume IPv4 targets).
            // -W 2: 2s per-reply timeout so one dead probe doesn't stall the rest.
            // timeout 15: hard cap on the whole command regardless of count,
            // since this call blocks the PHP request/response cycle.
            $cmd = sprintf(
                'timeout 15 ping -4 -I %s -c %d -W 2 %s 2>&1',
                escapeshellarg($device),
                $count,
                escapeshellarg($host)
            );

            Log::info("Running ping: {$cmd}");
            $output = shell_exec($cmd) ?? '';

            return response()->json([
                'success' => true,
                'command' => "ping -I {$device} -c {$count} {$host}",
                'output'  => $output,
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['success' => false, 'errors' => $e->errors()], 422);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 422);
        } catch (\Exception $e) {
            Log::error('Ping error: ' . $e->getMessage());
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function traceroute(Request $request)
    {
        try {
            $validated = $request->validate([
                'device' => 'required|string',
                'host'   => 'required|string|max:253',
            ]);

            $this->assertKnownEthDevice($validated['device']);

            $host = $this->sanitizeHost($validated['host']);
            if ($host === null) {
                return response()->json(['success' => false, 'error' => 'Invalid hostname or IP address.'], 422);
            }

            $device = $validated['device'];

            // -n: skip reverse DNS lookups (faster, avoids extra outbound
            //     traffic on other interfaces).
            // -w 2 -q 1 -m 15: 2s per-probe wait, 1 probe per hop, 15 hop
            //     ceiling — trades a little detail for a bounded run time.
            // timeout 20: hard cap on the whole command.
            $cmd = sprintf(
                'timeout 20 traceroute -4 -i %s -n -w 2 -q 1 -m 15 %s 2>&1',
                escapeshellarg($device),
                escapeshellarg($host)
            );

            Log::info("Running traceroute: {$cmd}");
            $output = shell_exec($cmd) ?? '';

            return response()->json([
                'success' => true,
                'command' => "traceroute -i {$device} {$host}",
                'output'  => $output,
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['success' => false, 'errors' => $e->errors()], 422);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 422);
        } catch (\Exception $e) {
            Log::error('Traceroute error: ' . $e->getMessage());
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
}