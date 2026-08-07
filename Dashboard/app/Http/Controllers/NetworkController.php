<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class NetworkController extends Controller
{
    /**
     * Execute a shell command with sudo and log everything.
     * Always returns a string (empty if command returns null).
     *
     * -n (non-interactive) makes sudo fail immediately with a clear
     * message on stderr if it would otherwise prompt for a password,
     * instead of hanging the request or silently returning nothing —
     * which is what happens when this runs under a web server user
     * (www-data, php-fpm pool user, etc.) that doesn't have a TTY.
     */
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

    /**
     * Detect sudo declining to run non-interactively (missing/blocked
     * password prompt) so it can be reported clearly instead of being
     * misread as "no device found".
     */
    private function isSudoAuthFailure(string $output): bool
    {
        return (bool) preg_match(
            '/a password is required|no tty present|askpass|sudo:.*password/i',
            $output
        );
    }

    /**
     * Find the actual device name for a given nmcli device TYPE
     * (e.g. "ethernet" or "wifi"). Interface names vary a lot between
     * Ubuntu/Raspberry Pi installs (eth0, end0, enp0s3, enx..., wlan0,
     * wlp2s0, ...) because of predictable network interface naming and
     * USB dongles, so we detect by type instead of assuming a name.
     * Prefers a connected device, falls back to the first one present.
     */
    private function detectDevice(string $type): string
    {
        $output = $this->runNmcli('nmcli -t -f DEVICE,TYPE,STATE device status');
        if (preg_match('/error|failed/i', $output)) {
            throw new \Exception("Failed to get device status: {$output}");
        }

        $candidates = [];
        foreach (explode("\n", trim($output)) as $line) {
            if (empty($line)) continue;
            $parts = explode(':', $line);
            if (count($parts) < 3) continue;
            [$device, $devType, $state] = [trim($parts[0]), trim($parts[1]), trim($parts[2])];
            if ($devType === $type) {
                $candidates[] = ['device' => $device, 'state' => $state];
            }
        }

        if (empty($candidates)) {
            throw new \Exception("No {$type} device found on this system.");
        }

        foreach ($candidates as $c) {
            if ($c['state'] === 'connected') {
                Log::debug("Detected {$type} device (connected): {$c['device']}");
                return $c['device'];
            }
        }

        Log::debug("Detected {$type} device (not connected): {$candidates[0]['device']}");
        return $candidates[0]['device'];
    }

    private function detectEthDevice(): string
    {
        return $this->detectDevice('ethernet');
    }

    private function detectWlanDevice(): string
    {
        return $this->detectDevice('wifi');
    }

    /**
     * Resolve the NetworkManager connection profile name bound to a device.
     * Checks for an active connection first, then falls back to any saved
     * profile whose TYPE matches (covers devices that exist but aren't
     * currently connected/activated).
     */
    private function resolveConnectionName(string $device, array $typeHints): string
    {
        // 1. Active connection on this device
        $output = $this->runNmcli('nmcli -t -f DEVICE,CONNECTION device status');
        if (preg_match('/error|failed/i', $output)) {
            throw new \Exception('Failed to get device status: ' . $output);
        }
        foreach (explode("\n", trim($output)) as $line) {
            if (empty($line)) continue;
            $parts = explode(':', $line);
            if (count($parts) >= 2 && trim($parts[0]) === $device) {
                $conn = trim($parts[1]);
                if (!empty($conn) && $conn !== '--') {
                    Log::debug("Found active connection for {$device}: {$conn}");
                    return $conn;
                }
            }
        }

        // 2. No active connection – find a saved profile of the right type
        $output = $this->runNmcli('nmcli -t -f NAME,TYPE con show');
        if (preg_match('/error|failed/i', $output)) {
            throw new \Exception('Failed to get connection list: ' . $output);
        }
        foreach (explode("\n", trim($output)) as $line) {
            if (empty($line)) continue;
            $parts = explode(':', $line);
            if (count($parts) < 2) continue;
            $name = trim($parts[0]);
            $type = trim($parts[1]);
            foreach ($typeHints as $hint) {
                if (strpos($type, $hint) !== false || $name === $device) {
                    Log::debug("Found connection profile for {$device}: {$name}");
                    return $name;
                }
            }
        }

        throw new \Exception("No connection profile found for {$device}.");
    }

    private function getWlanConnectionName(): string
    {
        $device = $this->detectWlanDevice();
        return $this->resolveConnectionName($device, ['wireless', '802-11', 'wifi']);
    }

    private function getEthConnectionName(): string
    {
        $device = $this->detectEthDevice();
        return $this->resolveConnectionName($device, ['ethernet', '802-3']);
    }

    /**
     * Parse nmcli -t output into key-value array.
     */
    private function parseNmcliShow(string $output): array
    {
        $data = [];
        foreach (explode("\n", $output) as $line) {
            $line = trim($line);
            if (empty($line)) continue;
            $parts = explode(':', $line, 2);
            if (count($parts) === 2) {
                $data[trim($parts[0])] = trim($parts[1]);
            }
        }
        return $data;
    }

    // ------------------------------------------------------------------------
    // Load
    // ------------------------------------------------------------------------

    public function index()
    {
        return view('server.network');
    }

    public function load()
    {
        Log::info('Network load requested');
        try {
            $eth = $this->loadEth();
            $wlan = $this->loadWlan();
            return response()->json(['success' => true, 'eth' => $eth, 'wlan' => $wlan]);
        } catch (\Exception $e) {
            Log::error('Load error: ' . $e->getMessage());
            Log::error($e->getTraceAsString());
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    private function loadWlan(): array
    {
        $device = $this->detectWlanDevice();
        $conn = $this->resolveConnectionName($device, ['wireless', '802-11', 'wifi']);
        $output = $this->runNmcli("nmcli -t -f ipv4.method,ipv4.addresses,ipv4.gateway,ipv4.dns,802-11-wireless.ssid con show " . escapeshellarg($conn));
        if (preg_match('/error|failed/i', $output)) {
            throw new \Exception('Failed to get connection details: ' . $output);
        }

        $data = $this->parseNmcliShow($output);

        $dhcp4 = ($data['ipv4.method'] ?? 'auto') === 'auto';
        $address = $data['ipv4.addresses'] ?? '';
        if (strpos($address, ',') !== false) {
            $address = explode(',', $address)[0];
        }

        return [
            'device'      => $device,
            'renderer'    => 'NetworkManager',
            'dhcp4'       => $dhcp4,
            'ssid'        => $data['802-11-wireless.ssid'] ?? '',
            'password'    => '',  // cannot retrieve
            'address'     => $address,
            'gateway'     => $data['ipv4.gateway'] ?? '',
            'nameservers' => str_replace(',', ', ', $data['ipv4.dns'] ?? ''),
        ];
    }

    private function loadEth(): array
    {
        $device = $this->detectEthDevice();
        $conn = $this->resolveConnectionName($device, ['ethernet', '802-3']);
        $output = $this->runNmcli("nmcli -t -f ipv4.method,ipv4.addresses,ipv4.gateway,ipv4.dns con show " . escapeshellarg($conn));
        if (preg_match('/error|failed/i', $output)) {
            throw new \Exception("Failed to get {$device} details: " . $output);
        }

        $data = $this->parseNmcliShow($output);

        $dhcp4 = ($data['ipv4.method'] ?? 'auto') === 'auto';
        $address = $data['ipv4.addresses'] ?? '';
        if (strpos($address, ',') !== false) {
            $address = explode(',', $address)[0];
        }

        return [
            'device'      => $device,
            'renderer'    => 'NetworkManager', // always for nmcli
            'dhcp4'       => $dhcp4,
            'address'     => $address,
            'gateway'     => $data['ipv4.gateway'] ?? '',
            'nameservers' => str_replace(',', ', ', $data['ipv4.dns'] ?? ''),
        ];
    }

    // ------------------------------------------------------------------------
    // Save
    // ------------------------------------------------------------------------

    public function save(Request $request)
    {
        Log::info('Save requested', $request->all());

        try {
            $validated = $request->validate([
                // Ethernet
                'eth.dhcp4'       => 'required|boolean',
                'eth.address'     => 'nullable|string|required_if:eth.dhcp4,false',
                'eth.gateway'     => 'nullable|ip|required_if:eth.dhcp4,false',
                'eth.nameservers' => 'nullable|string',

                // WiFi (existing)
                'wlan.dhcp4'       => 'required|boolean',
                'wlan.ssid'        => 'required|string',
                'wlan.password'    => 'nullable|string',
                'wlan.address'     => 'nullable|string|required_if:wlan.dhcp4,false',
                'wlan.gateway'     => 'nullable|ip|required_if:wlan.dhcp4,false',
                'wlan.nameservers' => 'nullable|string',
            ]);

            $this->saveEth($validated['eth']);
            $this->saveWlan($validated['wlan']);

            return response()->json(['success' => true, 'message' => 'Network settings updated']);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['success' => false, 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            Log::error('Save error: ' . $e->getMessage());
            Log::error($e->getTraceAsString());
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    private function saveWlan(array $data): void
    {
        $conn = $this->getWlanConnectionName();

        // ----- 1. IP settings -----
        if ($data['dhcp4'] === false) {
            $address = $data['address'];
            $gateway = $data['gateway'];
            $dns = str_replace(',', ' ', $data['nameservers'] ?? '');
            $dns = trim($dns);

            $cmd = sprintf(
                'nmcli con mod %s ipv4.method manual ipv4.addresses %s ipv4.gateway %s ipv4.dns %s',
                escapeshellarg($conn),
                escapeshellarg($address),
                escapeshellarg($gateway),
                escapeshellarg($dns)
            );
            $output = $this->runNmcli($cmd);
            if (preg_match('/error|failed/i', $output)) {
                throw new \Exception("Failed to set static IP: $output");
            }
        } else {
            $cmd = sprintf(
                'nmcli con mod %s ipv4.method auto ipv4.addresses "" ipv4.gateway "" ipv4.dns ""',
                escapeshellarg($conn)
            );
            $output = $this->runNmcli($cmd);
            if (preg_match('/error|failed/i', $output)) {
                throw new \Exception("Failed to set DHCP: $output");
            }
        }

        // ----- 2. SSID and password -----
        if (!empty($data['ssid'])) {
            $cmd = sprintf('nmcli con mod %s 802-11-wireless.ssid %s', escapeshellarg($conn), escapeshellarg($data['ssid']));
            $output = $this->runNmcli($cmd);
            if (preg_match('/error|failed/i', $output)) {
                throw new \Exception("Failed to set SSID: $output");
            }
        }

        if (!empty($data['password'])) {
            $cmd = sprintf(
                'nmcli con mod %s 802-11-wireless-security.key-mgmt wpa-psk 802-11-wireless-security.psk %s',
                escapeshellarg($conn),
                escapeshellarg($data['password'])
            );
            $output = $this->runNmcli($cmd);
            if (preg_match('/error|failed/i', $output)) {
                // Fallback: try separately
                $this->runNmcli(sprintf('nmcli con mod %s 802-11-wireless-security.key-mgmt wpa-psk', escapeshellarg($conn)));
                $output = $this->runNmcli(sprintf('nmcli con mod %s 802-11-wireless-security.psk %s', escapeshellarg($conn), escapeshellarg($data['password'])));
                if (preg_match('/error|failed/i', $output)) {
                    throw new \Exception("Failed to set password: $output");
                }
            }
        }

        // ----- 3. Restart connection to apply changes -----
        $this->runNmcli("nmcli con up " . escapeshellarg($conn));
        Log::info("WiFi connection restarted");
    }

    private function saveEth(array $data): void
    {
        $conn = $this->getEthConnectionName();

        if ($data['dhcp4'] === false) {
            $address = $data['address'];
            $gateway = $data['gateway'];
            $dns = str_replace(',', ' ', $data['nameservers'] ?? '');
            $dns = trim($dns);

            $cmd = sprintf(
                'nmcli con mod %s ipv4.method manual ipv4.addresses %s ipv4.gateway %s ipv4.dns %s',
                escapeshellarg($conn),
                escapeshellarg($address),
                escapeshellarg($gateway),
                escapeshellarg($dns)
            );
            $output = $this->runNmcli($cmd);
            if (preg_match('/error|failed/i', $output)) {
                throw new \Exception("Failed to set eth0 static IP: $output");
            }
        } else {
            $cmd = sprintf(
                'nmcli con mod %s ipv4.method auto ipv4.addresses "" ipv4.gateway "" ipv4.dns ""',
                escapeshellarg($conn)
            );
            $output = $this->runNmcli($cmd);
            if (preg_match('/error|failed/i', $output)) {
                throw new \Exception("Failed to set eth0 DHCP: $output");
            }
        }

        // Optionally restart eth0 (if it's up)
        /* $this->runNmcli("nmcli con up " . escapeshellarg($conn));
        Log::info("Ethernet connection restarted"); */
    }

    public function restartEth()
    {
        Log::info('Ethernet restart requested');
        try {
            $conn = $this->getEthConnectionName();
            $output = $this->runNmcli("nmcli con up " . escapeshellarg($conn));
            Log::info("Ethernet connection restarted");
            return response()->json(['success' => true, 'message' => 'Ethernet restarted successfully']);
        } catch (\Exception $e) {
            Log::error('Restart eth error: ' . $e->getMessage());
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
}