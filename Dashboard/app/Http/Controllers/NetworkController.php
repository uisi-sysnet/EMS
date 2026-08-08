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
     * Return the device names of every nmcli device of the given TYPE
     * (e.g. "ethernet" or "wifi"), in the order nmcli reports them.
     * Interface names vary a lot between Ubuntu/Raspberry Pi installs
     * (eth0, end0, enp0s3, enx..., wlan0, wlp2s0, ...) because of
     * predictable network interface naming and USB dongles, so we
     * detect by type instead of assuming a name or a fixed count.
     *
     * Returns an empty array — never throws — when no device of this
     * type is present. Not having a WiFi or a second Ethernet adapter
     * is a normal system configuration, not an error condition.
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
     * Resolve the NetworkManager connection profile name bound to a device.
     * Checks for an active connection first, then falls back to a saved
     * profile explicitly bound to that device (via connection.interface-name
     * or a matching profile name). Only falls further back to an unbound
     * profile of the right type if nothing device-specific is found —
     * needed for single-NIC systems, but skipped whenever a device-specific
     * match exists so multi-port hardware doesn't get every port pointed
     * at the same profile.
     */
    private function resolveConnectionName(string $device, array $typeHints): string
    {
        // 1. Active connection currently bound to this device
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

        // 2. No active connection — check saved profiles of the right type
        // and match by their explicit connection.interface-name binding.
        // This matters on multi-port hardware: several ethernet profiles
        // can exist at once (one per port, e.g. from netplan), so matching
        // "any profile of type ethernet" would silently hand every port
        // the same profile. Only an unbound profile (no interface-name
        // set) is used as a last-resort fallback, and only if nothing
        // more specific was found.
        $output = $this->runNmcli('nmcli -t -f NAME,TYPE con show');
        if (preg_match('/error|failed/i', $output)) {
            throw new \Exception('Failed to get connection list: ' . $output);
        }

        $candidates = [];
        foreach (explode("\n", trim($output)) as $line) {
            if (empty($line)) continue;
            $parts = explode(':', $line);
            if (count($parts) < 2) continue;
            $name = trim($parts[0]);
            $type = trim($parts[1]);
            foreach ($typeHints as $hint) {
                if (strpos($type, $hint) !== false) {
                    $candidates[] = $name;
                    break;
                }
            }
        }

        $unbound = null;
        foreach ($candidates as $name) {
            if ($name === $device) {
                Log::debug("Found connection profile for {$device} (name match): {$name}");
                return $name;
            }

            $ifaceOutput = $this->runNmcli('nmcli -t -f connection.interface-name con show ' . escapeshellarg($name));
            if (preg_match('/error|failed/i', $ifaceOutput)) {
                continue; // profile may have vanished between listing and lookup; skip it
            }
            $iface = $this->parseNmcliShow($ifaceOutput)['connection.interface-name'] ?? '';

            if ($iface === $device) {
                Log::debug("Found connection profile for {$device} (bound): {$name}");
                return $name;
            }

            if ($iface === '' && $unbound === null) {
                $unbound = $name;
            }
        }

        if ($unbound !== null) {
            Log::debug("No profile bound to {$device}; falling back to unbound profile: {$unbound}");
            return $unbound;
        }

        throw new \Exception("No connection profile found for {$device}.");
    }

    private function getEthConnectionName(string $device): string
    {
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

    /**
     * Return the device currently holding the system's default IPv4 route,
     * read straight from the kernel routing table. This reflects reality
     * regardless of how many connections have a gateway configured, so it's
     * used to show the current default in the UI (as opposed to inferring
     * it from per-connection nmcli settings).
     *
     * Returns null if there is no default route at all.
     */
    private function getDefaultGatewayDevice(): ?string
    {
        $output = trim((string) shell_exec('ip -4 route show default 2>&1'));
        if ($output === '') {
            return null;
        }

        // Example: "default via 192.168.1.1 dev eth0 proto dhcp metric 100"
        if (preg_match('/\bdev\s+(\S+)/', $output, $m)) {
            return $m[1];
        }

        return null;
    }

    /**
     * Set which Ethernet device's gateway should act as the system's
     * default route, via NetworkManager's ipv4.never-default flag:
     * - the chosen device gets "no" (allowed to be the default)
     * - every other device gets "yes" (excluded from being the default)
     * Passing $device = null clears the override on every device
     * ("Automatic" in the UI), letting NetworkManager fall back to its
     * own route-metric based selection.
     *
     * Devices that exist but have no resolvable connection profile are
     * skipped with a logged warning rather than failing the whole save,
     * matching how loadAllEth() treats the same situation.
     */
    private function setDefaultGatewayDevice(?string $device, array $allDevices): void
    {
        foreach ($allDevices as $d) {
            try {
                $conn = $this->getEthConnectionName($d);
            } catch (\Exception $e) {
                Log::warning("Skipping default-gateway update for {$d}: " . $e->getMessage());
                continue;
            }

            $neverDefault = ($device === null || $d === $device) ? 'no' : 'yes';

            $cmd = sprintf(
                'nmcli con mod %s ipv4.never-default %s',
                escapeshellarg($conn),
                escapeshellarg($neverDefault)
            );
            $output = $this->runNmcli($cmd);
            if (preg_match('/error|failed/i', $output)) {
                Log::error("Failed to set ipv4.never-default for {$d}: {$output}");
                continue;
            }

            // Reactivate so the routing table picks up the change immediately.
            // Non-fatal: if it fails, the setting is still saved and takes
            // effect on the next connection restart or reboot.
            $upOutput = $this->runNmcli('nmcli con up ' . escapeshellarg($conn));
            if (preg_match('/error|failed/i', $upOutput)) {
                Log::warning("{$d} reactivation after default-gateway change failed: {$upOutput}");
            }
        }
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
            $eth = $this->loadAllEth();
            $defaultGatewayDevice = $this->getDefaultGatewayDevice();
            return response()->json([
                'success' => true,
                'eth' => $eth,
                'default_gateway_device' => $defaultGatewayDevice,
            ]);
        } catch (\Exception $e) {
            Log::error('Load error: ' . $e->getMessage());
            Log::error($e->getTraceAsString());
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Load every Ethernet device present on the system. A device that
     * exists but fails to resolve its own details (e.g. no bound
     * connection profile yet) is skipped with a logged warning instead
     * of failing the whole page — an empty result here just means the
     * UI shows no Ethernet cards, not an error banner.
     */
    private function loadAllEth(): array
    {
        $devices = $this->detectEthDevices();
        $result = [];
        foreach ($devices as $device) {
            try {
                $result[] = $this->loadEthDevice($device);
            } catch (\Exception $e) {
                Log::warning("Skipping {$device}: " . $e->getMessage());
            }
        }
        return $result;
    }

    private function loadEthDevice(string $device): array
    {
        $conn = $this->getEthConnectionName($device);
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
                // Ethernet — an array of per-device objects, one per
                // changed interface. 'device' pins each item to the
                // right nmcli connection.
                'eth'               => 'sometimes|array',
                'eth.*.device'      => 'required|string',
                'eth.*.dhcp4'       => 'required|boolean',
                'eth.*.address'     => 'nullable|string|required_if:eth.*.dhcp4,false',
                'eth.*.gateway'     => 'nullable|ip|required_if:eth.*.dhcp4,false',
                'eth.*.nameservers' => 'nullable|string',

                // Which Ethernet device's gateway is the system default.
                // Present only when the user changed the selector; null
                // means "Automatic" (clear the override on every device).
                'default_gateway'   => 'sometimes|nullable|string',
            ]);

            $updated = [];

            foreach ($validated['eth'] ?? [] as $ethItem) {
                $this->saveEth($ethItem['device'], $ethItem);
                $updated[] = $ethItem['device'];
            }

            $defaultGatewaySubmitted = array_key_exists('default_gateway', $validated);
            if ($defaultGatewaySubmitted) {
                $this->setDefaultGatewayDevice($validated['default_gateway'] ?: null, $this->detectEthDevices());
            }

            if (empty($updated) && !$defaultGatewaySubmitted) {
                return response()->json(['success' => false, 'error' => 'No changes were submitted.'], 422);
            }

            $messages = [];
            if (!empty($updated)) {
                $messages[] = implode(', ', $updated) . ' updated';
            }
            if ($defaultGatewaySubmitted) {
                $messages[] = 'default gateway set to ' . ($validated['default_gateway'] ?: 'automatic');
            }

            return response()->json(['success' => true, 'message' => implode('; ', $messages)]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['success' => false, 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            Log::error('Save error: ' . $e->getMessage());
            Log::error($e->getTraceAsString());
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    private function saveEth(string $device, array $data): void
    {
        $conn = $this->getEthConnectionName($device);

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
                throw new \Exception("Failed to set {$device} static IP: $output");
            }
        } else {
            $cmd = sprintf(
                'nmcli con mod %s ipv4.method auto ipv4.addresses "" ipv4.gateway "" ipv4.dns ""',
                escapeshellarg($conn)
            );
            $output = $this->runNmcli($cmd);
            if (preg_match('/error|failed/i', $output)) {
                throw new \Exception("Failed to set {$device} DHCP: $output");
            }
        }

        // Restart the connection so the new settings (static IP/gateway/DNS
        // or a switch back to DHCP) actually take effect. Non-fatal: if the
        // restart fails, the config is still saved and applies on the next
        // reboot or a manual "Restart Connection" click.
        $output = $this->runNmcli("nmcli con up " . escapeshellarg($conn));
        if (preg_match('/error|failed/i', $output)) {
            Log::error("{$device} restart after save failed: {$output}");
        } else {
            Log::info("{$device} connection restarted");
        }
    }

    public function restartEth(Request $request)
    {
        $device = $request->input('device');
        Log::info("Ethernet restart requested for " . ($device ?: '(none specified)'));
        try {
            if (empty($device)) {
                throw new \Exception('No device specified.');
            }
            $conn = $this->getEthConnectionName($device);
            $this->runNmcli("nmcli con up " . escapeshellarg($conn));
            Log::info("{$device} connection restarted");
            return response()->json(['success' => true, 'message' => "{$device} restarted successfully"]);
        } catch (\Exception $e) {
            Log::error('Restart eth error: ' . $e->getMessage());
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
}