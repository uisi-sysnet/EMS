<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class NetworkController extends Controller
{
    /**
     * Execute a shell command with sudo and log everything.
     * Always returns a string (empty if command returns null).
     */
    private function runNmcli(string $command): string
    {
        $fullCmd = 'sudo ' . $command . ' 2>&1';
        Log::debug("Executing: {$fullCmd}");
        $output = shell_exec($fullCmd);
        Log::debug("Output: " . ($output ?: '(empty)'));
        return $output ?? '';
    }

    /**
     * Get the NetworkManager connection name for eth0.
     * If no active connection and no existing profile is found,
     * automatically create a new DHCP connection named "eth0" and bring it up.
     */
    private function getEthConnectionName(): string
    {
        // 1. Check if eth0 has an active connection
        $output = $this->runNmcli('nmcli -t -f DEVICE,CONNECTION device status');
        if (!preg_match('/error|failed/i', $output)) {
            $lines = explode("\n", trim($output));
            foreach ($lines as $line) {
                if (empty($line)) continue;
                $parts = explode(':', $line);
                if (count($parts) >= 2 && trim($parts[0]) === 'eth0') {
                    $conn = trim($parts[1]);
                    if (!empty($conn) && $conn !== '--') {
                        Log::debug("Found Ethernet connection (active): {$conn}");
                        return $conn;
                    }
                }
            }
        }

        // 2. If no active connection, look for a profile named 'netplan-eth0' or any ethernet
        $output = $this->runNmcli('nmcli -t -f NAME,TYPE con show');
        if (!preg_match('/error|failed/i', $output)) {
            $lines = explode("\n", trim($output));
            foreach ($lines as $line) {
                if (empty($line)) continue;
                $parts = explode(':', $line);
                if (count($parts) >= 2) {
                    $name = trim($parts[0]);
                    $type = trim($parts[1]);
                    if (strpos($type, 'ethernet') !== false || $name === 'netplan-eth0' || $name === 'eth0') {
                        Log::debug("Found Ethernet profile: {$name}");
                        return $name;
                    }
                }
            }
        }

        // 3. No connection found – create a new DHCP connection for eth0
        Log::info('No Ethernet connection found. Creating a new one with DHCP.');
        $createOutput = $this->runNmcli('nmcli con add type ethernet ifname eth0 con-name eth0 autoconnect yes');
        if (preg_match('/error|failed/i', $createOutput)) {
            throw new \Exception('Failed to create Ethernet connection: ' . $createOutput);
        }

        // Bring it up immediately
        $upOutput = $this->runNmcli('nmcli con up eth0');
        if (preg_match('/error|failed/i', $upOutput)) {
            Log::warning('Could not bring up newly created Ethernet connection: ' . $upOutput);
        }

        return 'eth0';
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
     * Get the current state of a network device (e.g., 'connected', 'disconnected').
     * Returns null if the device does not exist.
     */
    private function getDeviceState(string $device): ?string
    {
        $output = $this->runNmcli('nmcli -t -f DEVICE,STATE device status');
        if (preg_match('/error|failed/i', $output)) {
            return null;
        }

        $lines = explode("\n", trim($output));
        foreach ($lines as $line) {
            if (empty($line)) continue;
            $parts = explode(':', $line);
            if (count($parts) >= 2 && trim($parts[0]) === $device) {
                return trim($parts[1]);  // e.g. "connected", "disconnected", "unavailable"
            }
        }
        return null;
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
            $ethState = $this->getDeviceState('eth0');

            return response()->json([
                'success'    => true,
                'eth'        => $eth,
                'eth_state'  => $ethState,
            ]);
        } catch (\Exception $e) {
            Log::error('Load error: ' . $e->getMessage());
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    private function loadEth(): array
    {
        $conn = $this->getEthConnectionName();
        $output = $this->runNmcli("nmcli -t -f ipv4.method,ipv4.addresses,ipv4.gateway,ipv4.dns con show " . escapeshellarg($conn));
        if (preg_match('/error|failed/i', $output)) {
            // If the connection exists but has no IP configuration yet (e.g., freshly created),
            // we simply return default DHCP values.
            Log::warning('Could not retrieve Ethernet details, assuming DHCP: ' . $output);
            return [
                'renderer'    => 'NetworkManager',
                'dhcp4'       => true,
                'address'     => '',
                'gateway'     => '',
                'nameservers' => '',
            ];
        }

        $data = $this->parseNmcliShow($output);

        $dhcp4 = ($data['ipv4.method'] ?? 'auto') === 'auto';
        $address = $data['ipv4.addresses'] ?? '';
        if (strpos($address, ',') !== false) {
            $address = explode(',', $address)[0];
        }

        return [
            'renderer'    => 'NetworkManager',
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
                'eth.dhcp4'       => 'required|boolean',
                'eth.address'     => 'nullable|string|required_if:eth.dhcp4,false',
                'eth.gateway'     => 'nullable|ip|required_if:eth.dhcp4,false',
                'eth.nameservers' => 'nullable|string',
            ]);

            $this->saveEth($validated['eth']);
            $this->restartEthConnection();

            return response()->json(['success' => true, 'message' => 'Ethernet settings updated and connection restarted']);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['success' => false, 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            Log::error('Save error: ' . $e->getMessage());
            Log::error($e->getTraceAsString());
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
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
                throw new \Exception("Failed to set Ethernet static IP: $output");
            }
        } else {
            $cmd = sprintf(
                'nmcli con mod %s ipv4.method auto ipv4.addresses "" ipv4.gateway "" ipv4.dns ""',
                escapeshellarg($conn)
            );
            $output = $this->runNmcli($cmd);
            if (preg_match('/error|failed/i', $output)) {
                throw new \Exception("Failed to set Ethernet DHCP: $output");
            }
        }
    }

    private function restartEthConnection(): void
    {
        try {
            $conn = $this->getEthConnectionName();
            $output = $this->runNmcli("nmcli con up " . escapeshellarg($conn));
            Log::info("Ethernet restarted: " . $output);
        } catch (\Exception $e) {
            Log::error("Failed to restart Ethernet: " . $e->getMessage());
        }
    }

    // ------------------------------------------------------------------------
    // Restart Endpoint (for the button)
    // ------------------------------------------------------------------------

    public function restartEth()
    {
        Log::info('Ethernet restart requested via button');
        try {
            $this->restartEthConnection();
            return response()->json(['success' => true, 'message' => 'Ethernet restarted successfully']);
        } catch (\Exception $e) {
            Log::error('Restart eth error: ' . $e->getMessage());
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
}