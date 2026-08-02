<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Symfony\Component\Yaml\Yaml;
use Illuminate\Support\Facades\Log;

class NetworkController extends Controller
{
    /**
     * Paths to the netplan YAML files.
     * Adjust these to match the actual filenames in /etc/netplan/ on your Raspberry Pi.
     */
    private function getEthPath()
    {
        // Replace with your Ethernet netplan file name ----- /etc/netplan/90-NM-75a1216a-9d1a-30cd-8aca-ace5526ec021.yaml
        return 'C:/Users/JUREEN/Desktop/Emergency-Warning-System-main/90-NM-75a1216a-9d1a-30cd-8aca-ace5526ec021.yaml';
    }

    private function getWlanPath()
    {
        // Replace with your WiFi netplan file name ----- /etc/netplan/90-NM-5c340202-1215-3f23-886f-5782d501a9ff.yaml
        return 'C:/Users/JUREEN/Desktop/Emergency-Warning-System-main/90-NM-5c340202-1215-3f23-886f-5782d501a9ff.yaml';
    }

    public function index()
    {
        return view('server.network');
    }

    public function load()
    {
        try {
            $ethConfig = $this->loadEth();
            $wlanConfig = $this->loadWlan();

            return response()->json([
                'success' => true,
                'eth'     => $ethConfig,
                'wlan'    => $wlanConfig,
            ]);
        } catch (\Exception $e) {
            Log::error('Network load error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error'   => $e->getMessage(),
            ]);
        }
    }

    private function loadEth()
    {
        $path = $this->getEthPath();
        if (!File::exists($path)) {
            throw new \Exception('Ethernet configuration file not found: ' . $path);
        }

        $yaml = File::get($path);
        $data = Yaml::parse($yaml);
        $eth = $data['network']['ethernets']['eth0'] ?? null;

        if (!$eth) {
            throw new \Exception('Could not find eth0 in the Ethernet YAML file.');
        }

        return [
            // Prefer per‑interface renderer, fallback to global if present
            'renderer'    => $eth['renderer'] ?? ($data['network']['renderer'] ?? 'networkd'),
            'dhcp4'       => $eth['dhcp4'] ?? false,
            'address'     => $eth['addresses'][0] ?? '',
            'gateway'     => $eth['routes'][0]['via'] ?? '',
            'nameservers' => implode(', ', $eth['nameservers']['addresses'] ?? []),
        ];
    }

    private function loadWlan()
    {
        $path = $this->getWlanPath();
        if (!File::exists($path)) {
            throw new \Exception('WiFi configuration file not found: ' . $path);
        }

        $yaml = File::get($path);
        $data = Yaml::parse($yaml);
        $wlan = $data['network']['wifis']['wlan0'] ?? null;

        if (!$wlan) {
            throw new \Exception('Could not find wlan0 in the WiFi YAML file.');
        }

        $accessPoints = $wlan['access-points'] ?? [];
        $ssid = array_key_first($accessPoints) ?? '';
        $password = $accessPoints[$ssid]['auth']['password'] ?? '';

        return [
            'renderer'    => $wlan['renderer'] ?? 'NetworkManager',
            'dhcp4'       => $wlan['dhcp4'] ?? true,
            'ssid'        => $ssid,
            'password'    => $password,
        ];
    }

    public function save(Request $request)
    {
        $validated = $request->validate([
            'eth.renderer'    => 'required|string|in:networkd,NetworkManager',
            'eth.dhcp4'       => 'required|boolean',
            'eth.address'     => 'required_if:eth.dhcp4,false|string',
            'eth.gateway'     => 'required_if:eth.dhcp4,false|ip',
            'eth.nameservers' => 'nullable|string',

            'wlan.renderer'   => 'required|string|in:networkd,NetworkManager',
            'wlan.dhcp4'      => 'required|boolean',
            'wlan.ssid'       => 'required|string',
            'wlan.password'   => 'required|string',
        ]);

        try {
            $this->saveEth($validated['eth']);
            $this->saveWlan($validated['wlan']);
            $this->applyNetplan();

            return response()->json([
                'success' => true,
                'message' => 'Network configurations updated and applied successfully.',
            ]);
        } catch (\Exception $e) {
            Log::error('Network save error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error'   => $e->getMessage(),
            ]);
        }
    }

    private function saveEth($data)
    {
        $path = $this->getEthPath();
        if (!File::exists($path)) {
            throw new \Exception('Ethernet file does not exist: ' . $path);
        }

        $yaml = File::get($path);
        $config = Yaml::parse($yaml);

        // Set per‑interface renderer only – no global renderer
        $eth = &$config['network']['ethernets']['eth0'];
        $eth['renderer'] = $data['renderer'];
        $eth['dhcp4'] = $data['dhcp4'];

        // Remove global renderer if it exists (to keep only one)
        unset($config['network']['renderer']);

        if ($data['dhcp4'] === true) {
            unset($eth['addresses']);
            unset($eth['routes']);
            unset($eth['nameservers']);
        } else {
            $eth['addresses'] = [$data['address']];
            $eth['routes'] = [
                ['to' => 'default', 'via' => $data['gateway']]
            ];

            if (!empty($data['nameservers'])) {
                $ns = array_map('trim', explode(',', $data['nameservers']));
                $ns = array_filter($ns);
                if (!empty($ns)) {
                    $eth['nameservers']['addresses'] = $ns;
                } else {
                    unset($eth['nameservers']);
                }
            } else {
                unset($eth['nameservers']);
            }
        }

        // Preserve match, dhcp6, networkmanager, etc.
        $newYaml = Yaml::dump($config, 4, 2);
        File::put($path, $newYaml);
    }

    private function saveWlan($data)
    {
        $path = $this->getWlanPath();
        if (!File::exists($path)) {
            throw new \Exception('WiFi file does not exist: ' . $path);
        }

        $yaml = File::get($path);
        $config = Yaml::parse($yaml);

        $wlan = &$config['network']['wifis']['wlan0'];
        $wlan['renderer'] = $data['renderer'];
        $wlan['dhcp4'] = $data['dhcp4'];

        // Remove global renderer if present
        unset($config['network']['renderer']);

        // Build access-points
        $accessPoints = [];
        $accessPoints[$data['ssid']] = [
            'auth' => [
                'key-management' => 'psk',
                'password'       => $data['password'],
            ],
        ];

        // Preserve existing networkmanager/passthrough fields from original
        $originalConfig = Yaml::parse($yaml);
        $origAp = $originalConfig['network']['wifis']['wlan0']['access-points'] ?? [];
        $origSsid = array_key_first($origAp);
        if ($origSsid) {
            foreach ($origAp[$origSsid] as $key => $val) {
                if ($key !== 'auth') {
                    $accessPoints[$data['ssid']][$key] = $val;
                }
            }
        }

        $wlan['access-points'] = $accessPoints;

        if (isset($originalConfig['network']['wifis']['wlan0']['networkmanager'])) {
            $wlan['networkmanager'] = $originalConfig['network']['wifis']['wlan0']['networkmanager'];
        }

        $newYaml = Yaml::dump($config, 4, 2);
        File::put($path, $newYaml);
    }

    private function applyNetplan()
    {
        $output = shell_exec('sudo netplan apply 2>&1');
        if ($output !== null && (strpos(strtolower($output), 'error') !== false || strpos(strtolower($output), 'failed') !== false)) {
            throw new \Exception('netplan apply failed: ' . $output);
        }
    }
}