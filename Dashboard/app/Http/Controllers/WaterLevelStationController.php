<?php

namespace App\Http\Controllers;

use App\Models\WaterLevel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class WaterLevelStationController extends Controller
{
    public function index()
    {
        $hasReadings = Schema::connection('water_level')->hasTable('sensor_data');

        $stations = WaterLevel::orderBy('station_mn')
            ->when($hasReadings, fn ($q) => $q->withCount('sensorData'))
            ->get();

        $deletedStations = WaterLevel::onlyTrashed()
            ->when($hasReadings, fn ($q) => $q->withCount('sensorData'))
            ->orderBy('station_mn')
            ->get();

        return view('inventory.water_level', compact('stations', 'deletedStations'));
    }

    public function store(Request $request)
    {
        $this->prepareSimNumber($request);

        $validated = $request->validate([
            'station_mn'          => ['required', 'string', 'max:14',
                                      Rule::unique('water_level.stations', 'station_mn')],
            'station_name'        => ['required', 'string', 'max:32'],
            'enabled'             => ['nullable', 'boolean'],
            'location'            => ['nullable', 'string', 'max:255'],
            'latitude'            => ['nullable', 'numeric', 'between:4.5,21.5'],
            'longitude'           => ['nullable', 'numeric', 'between:116,127'],
            'installation_height' => ['nullable', 'numeric'],
            'elevation_height'    => ['nullable', 'numeric'],
            'lead_ip'             => ['nullable', 'ip', 'max:15',
                                      Rule::unique('water_level.stations', 'lead_ip')],
            'lead_port'           => ['nullable', 'integer', 'min:1', 'max:65535'],
            'lead_slave'          => ['nullable', 'integer', 'min:1', 'max:255'],
            'sim_number'          => ['nullable', 'required_without:lead_ip', 'regex:/^\+[1-9]\d{9,14}$/',
                                      Rule::unique('water_level.stations', 'sim_number')],
            'report_interval_minutes' => ['nullable', 'integer', 'min:1', 'max:1440'],
        ], $this->messages());

        $validated['enabled'] = $request->boolean('enabled', true);
        $validated['report_interval_minutes'] ??= 3;

        WaterLevel::create($validated);

        return redirect()
            ->route('inventory.water-level-stations.index')
            ->with('success', "Station \"{$validated['station_mn']}\" created successfully.");
    }

    public function edit($station_mn)
    {
        $station = WaterLevel::where('station_mn', $station_mn)->firstOrFail();

        return response()->json([
            'station_mn'          => $station->station_mn,
            'station_name'        => $station->station_name,
            'enabled'             => $station->enabled,
            'location'            => $station->location,
            'latitude'            => $station->latitude,
            'longitude'           => $station->longitude,
            'installation_height' => $station->installation_height,
            'elevation_height'    => $station->elevation_height,
            'lead_ip'             => $station->lead_ip,
            'lead_port'           => $station->lead_port,
            'lead_slave'          => $station->lead_slave,
            'sim_number'          => $station->sim_number,
            'report_interval_minutes'  => $station->report_interval_minutes,
            'applied_interval_minutes' => $station->applied_interval_minutes,
            'interval_pending'         => $station->intervalPending(),
        ]);
    }

    public function update(Request $request, $station_mn)
    {
        $station = WaterLevel::where('station_mn', $station_mn)->firstOrFail();
        $this->prepareSimNumber($request);

        $validated = $request->validate([
            'station_mn'          => ['required', 'string', 'max:14',
                                      Rule::unique('water_level.stations', 'station_mn')
                                          ->ignore($station->id)],
            'station_name'        => ['required', 'string', 'max:32'],
            'enabled'             => ['nullable', 'boolean'],
            'location'            => ['nullable', 'string', 'max:255'],
            'latitude'            => ['nullable', 'numeric', 'between:4.5,21.5'],
            'longitude'           => ['nullable', 'numeric', 'between:116,127'],
            'installation_height' => ['nullable', 'numeric'],
            'elevation_height'    => ['nullable', 'numeric'],
            'lead_ip'             => ['nullable', 'ip', 'max:15',
                                      Rule::unique('water_level.stations', 'lead_ip')
                                          ->ignore($station->id)],
            'lead_port'           => ['nullable', 'integer', 'min:1', 'max:65535'],
            'lead_slave'          => ['nullable', 'integer', 'min:1', 'max:255'],
            'sim_number'          => ['nullable', 'required_without:lead_ip', 'regex:/^\+[1-9]\d{9,14}$/',
                                      Rule::unique('water_level.stations', 'sim_number')
                                          ->ignore($station->id)],
            'report_interval_minutes' => ['nullable', 'integer', 'min:1', 'max:1440'],
        ], $this->messages());

        $validated['enabled'] = $request->boolean('enabled', true);
        $validated['report_interval_minutes'] ??= $station->report_interval_minutes ?? 15;

        $station->fill($validated);
        // A new interval or SIM must be (re)sent to the sensor right away;
        // water_level_gsm.py picks up stations whose interval_sent_at is null.
        if ($station->isDirty(['report_interval_minutes', 'sim_number'])) {
            $station->interval_sent_at = null;
        }
        $station->save();

        return redirect()
            ->route('inventory.water-level-stations.index')
            ->with('success', "Station \"{$station->station_mn}\" updated successfully.");
    }

    public function destroy($station_mn)
    {
        $station = WaterLevel::where('station_mn', $station_mn)->firstOrFail();
        $station->delete();

        return redirect()
            ->route('inventory.water-level-stations.index')
            ->with('success', "Station \"{$station->station_mn}\" deleted successfully.");
    }

    public function restore($station_mn)
    {
        $station = WaterLevel::onlyTrashed()
            ->where('station_mn', $station_mn)
            ->firstOrFail();

        $station->restore();

        return redirect()
            ->route('inventory.water-level-stations.index')
            ->with('success', "Station \"{$station->station_mn}\" restored successfully.");
    }

    /**
     * Normalise the SIM number before validation so 0917..., 63917... and
     * +63 917-... all become +63917..., the form water_level_gsm.py matches
     * incoming SMS senders against. An empty IP becomes null (GSM stations).
     */
    protected function prepareSimNumber(Request $request): void
    {
        $sim = preg_replace('/[\s\-().]/', '', (string) $request->input('sim_number', ''));

        if ($sim === '') {
            $sim = null;
        } elseif (preg_match('/^0(9\d{9})$/', $sim, $m)) {   // Philippine mobile, local format
            $sim = '+63' . $m[1];
        } elseif (preg_match('/^63\d{10}$/', $sim)) {
            $sim = '+' . $sim;
        }
        $request->merge(['sim_number' => $sim]);

        if (trim((string) $request->input('lead_ip', '')) === '') {
            $request->merge(['lead_ip' => null]);
        }
    }

    private function messages(): array
    {
        return [
            'station_mn.unique'           => 'This Station MN already exists (including deleted stations).',
            'lead_ip.unique'              => 'This IP Address is already assigned to another station (including deleted stations).',
            'sim_number.required_without' => 'Enter a SIM number or an IP address.',
            'sim_number.regex'            => 'Enter the SIM number as 09XXXXXXXXX or in international format (+63...).',
            'sim_number.unique'           => 'This SIM number is already assigned to another station (including deleted stations).',
        ];
    }

    /**
     * How many readings a station has in IOT_water_level.sensor_data, for
     * the delete confirmation. Reports zero if the table isn't there yet.
     */
    public function checkData($station_mn)
    {
        $exists = WaterLevel::withTrashed()
            ->where('station_mn', $station_mn)
            ->exists();

        $dataCount = Schema::connection('water_level')->hasTable('sensor_data')
            ? DB::connection('water_level')->table('sensor_data')->where('station_mn', $station_mn)->count()
            : 0;

        return response()->json([
            'hasData'   => $dataCount > 0,
            'dataCount' => $dataCount,
            'exists'    => $exists,
        ]);
    }

    // ------------------------------------------------------------------
    // GSM gateway (master Nano + SIM800L on a USB serial port)
    // ------------------------------------------------------------------

    private const GSM_SERVICE = 'ems-water-level-gsm.service';

    /** USB serial chips the gateway Nano usually has. vendor:product => name */
    private const KNOWN_USB_SERIAL = [
        '1a86:7523' => 'CH340 (most Arduino Nano clones)',
        '1a86:55d4' => 'CH9102 (newer Nano clones)',
        '0403:6001' => 'FTDI FT232R (original Arduino Nano)',
        '10c4:ea60' => 'CP2102',
        '2341:0043' => 'Arduino Uno',
        '2341:0058' => 'Arduino Nano Every',
    ];

    /**
     * The GSM gateway's current setting, the serial ports found on this
     * server, the service state and its latest messages (Water Level page).
     */
    public function gateway()
    {
        $env = $this->readScriptsEnv();
        $configured = $env['WATER_GSM_SERIAL_PORT'] ?? '/dev/ttyUSB0';
        $ports = self::detectSerialPorts();
        $configuredDevice = @realpath($configured) ?: null;

        foreach ($ports as &$port) {
            $port['selected'] = $port['path'] === $configured
                || ($configuredDevice !== null && $port['device'] === $configuredDevice);
        }
        unset($port);

        // Link state from the service's own messages, so the panel can show
        // whether Connect worked: "Opened…" -> "GSM gateway ready", or a link problem.
        $link = null;
        try {
            $last = \App\Models\SystemLog::where('service', 'water_level_gsm')
                ->where(fn ($q) => $q->where('message', 'like', 'Opened GSM gateway on%')
                    ->orWhere('message', 'like', 'GSM gateway ready%')
                    ->orWhere('message', 'like', 'GSM gateway link problem%'))
                ->orderByDesc('created_at')->first(['created_at', 'message']);
            if ($last) {
                $link = [
                    'state'   => str_starts_with($last->message, 'GSM gateway ready') ? 'ready'
                        : (str_starts_with($last->message, 'Opened') ? 'opening' : 'error'),
                    'at'      => $last->created_at?->timestamp,
                    'time'    => $last->created_at?->timezone('Asia/Manila')->format('M j, H:i:s'),
                    'message' => $last->message,
                ];
            }
        } catch (\Throwable $e) {
            report($e);
        }

        $logs = [];
        try {
            $logs = \App\Models\SystemLog::where('service', 'water_level_gsm')
                ->orderByDesc('created_at')->limit(8)
                ->get(['created_at', 'level', 'message'])
                ->map(fn ($l) => [
                    'time'    => $l->created_at?->timezone('Asia/Manila')->format('M j, H:i:s'),
                    'level'   => $l->level,
                    'message' => $l->message,
                ]);
        } catch (\Throwable $e) {
            report($e);
        }

        return response()->json([
            'enabled'          => in_array(strtolower($env['WATER_GSM_ENABLED'] ?? 'false'), ['1', 'true', 'yes', 'on'], true),
            'configured_port'  => $configured,
            'configured_found' => $configuredDevice !== null,
            'baudrate'         => $env['WATER_GSM_BAUDRATE'] ?? '57600',
            'service'          => $this->unitState(self::GSM_SERVICE),
            'ports'            => $ports,
            'warnings'         => $this->serialWarnings(),
            'link'             => $link,
            'server_time'      => now()->timestamp,
            'logs'             => $logs,
        ]);
    }

    /**
     * Saves the chosen port as WATER_GSM_SERIAL_PORT, turns the gateway on
     * (WATER_GSM_ENABLED=true) and restarts the GSM service.
     */
    public function gatewaySave(Request $request)
    {
        $paths = array_column(self::detectSerialPorts(), 'path');
        $validated = $request->validate([
            'port' => ['required', 'string', Rule::in($paths)],
        ], ['port.in' => 'That port is no longer connected. Refresh the list and pick again.']);

        // Taken before the restart: the service may log "Opened…" while it restarts.
        $since = now()->timestamp;
        $error = $this->writeScriptsEnv([
            'WATER_GSM_SERIAL_PORT' => $validated['port'],
            'WATER_GSM_ENABLED'     => 'true',
        ]);
        if ($error) {
            return response()->json(['ok' => false, 'error' => $error], 500);
        }

        $restart = new \Symfony\Component\Process\Process(['sudo', '/usr/bin/systemctl', 'restart', self::GSM_SERVICE]);
        $restart->setTimeout(30);
        $restart->run();

        return response()->json([
            'ok'      => $restart->isSuccessful(),
            'port'    => $validated['port'],
            'since'   => $since,
            'message' => $restart->isSuccessful()
                ? "Saved {$validated['port']} and restarted the GSM gateway service."
                : "Saved {$validated['port']}, but the service didn't restart: " . trim($restart->getErrorOutput() ?: $restart->getOutput())
                  . ' Restart it on the Services page or run: sudo systemctl restart ' . self::GSM_SERVICE,
        ]);
    }

    /**
     * USB serial ports on this (Linux) server. Prefers the stable
     * /dev/serial/by-id/... name, which survives reboots and re-plugging;
     * /dev/ttyUSB0 can change to ttyUSB1. $root is for the self-check only.
     *
     * @return array<int, array{path: string, device: string, name: string, vendor_product: ?string, chip: ?string, likely_gateway: bool}>
     */
    public static function detectSerialPorts(string $root = ''): array
    {
        $byDevice = [];
        foreach (glob("{$root}/dev/serial/by-id/*") ?: [] as $link) {
            $device = realpath($link);
            if ($device) {
                $byDevice[$device] = $link;
            }
        }
        // USB adapters, plus built-in / on-board UARTs (ttyS*, Raspberry Pi
        // ttyAMA*, Jetson ttyTHS*). Linux always creates ttyS0-31; only the
        // ones with real hardware have a non-zero UART type in sysfs.
        $candidates = [];
        foreach (['ttyUSB*', 'ttyACM*', 'ttyAMA*', 'ttyTHS*', 'ttyS*'] as $pattern) {
            $candidates = array_merge($candidates, glob("{$root}/dev/{$pattern}") ?: []);
        }
        foreach ($candidates as $device) {
            $tty = basename($device);
            $type = "{$root}/sys/class/tty/{$tty}/type";
            if (str_starts_with($tty, 'ttyS') && (!is_file($type) || trim((string) @file_get_contents($type)) === '0')) {
                continue;   // placeholder with no hardware behind it
            }
            $device = realpath($device) ?: $device;
            $byDevice[$device] ??= $device;
        }

        $ports = [];
        foreach ($byDevice as $device => $path) {
            $tty = basename($device);
            $usb = self::usbInfo("{$root}/sys/class/tty/{$tty}/device");
            $id = $usb ? "{$usb['vendor']}:{$usb['product']}" : null;
            $ports[] = [
                'path'           => $root === '' ? $path : substr($path, strlen($root)),
                'device'         => $root === '' ? $device : substr($device, strlen($root)),
                'name'           => trim(($usb['manufacturer'] ?? '') . ' ' . ($usb['product_name'] ?? ''))
                                    ?: (preg_match('/^tty(S|AMA|THS)\d/', $tty) ? "Built-in serial port ({$tty})" : $tty),
                'vendor_product' => $id,
                'chip'           => $id ? (self::KNOWN_USB_SERIAL[$id] ?? null) : null,
                'likely_gateway' => $id !== null && isset(self::KNOWN_USB_SERIAL[$id]),
            ];
        }
        usort($ports, fn ($a, $b) => [$b['likely_gateway'], $a['path']] <=> [$a['likely_gateway'], $b['path']]);

        return $ports;
    }

    /** USB ids for a tty: idVendor/idProduct live a few levels above it in sysfs. */
    private static function usbInfo(string $sysDevice): ?array
    {
        $dir = realpath($sysDevice);
        for ($i = 0; $dir && $i < 4; $i++, $dir = dirname($dir)) {
            if (is_file("{$dir}/idVendor")) {
                $read = fn ($f) => is_file("{$dir}/{$f}") ? trim((string) @file_get_contents("{$dir}/{$f}")) : null;
                return [
                    'vendor'       => strtolower((string) $read('idVendor')),
                    'product'      => strtolower((string) $read('idProduct')),
                    'manufacturer' => $read('manufacturer'),
                    'product_name' => $read('product'),
                ];
            }
        }
        return null;
    }

    /** Ubuntu services known to grab or disturb a USB serial gateway. */
    private function serialWarnings(): array
    {
        $warnings = [];
        if (in_array($this->unitState('brltty.service'), ['active', 'activating'], true) || is_file('/usr/bin/brltty')) {
            $warnings[] = 'brltty (a braille display service) is installed. On Ubuntu it takes over CH340 USB chips, used on most Nano clones, and the port disappears. Remove it: sudo apt remove brltty';
        }
        if (in_array($this->unitState('ModemManager.service'), ['active', 'activating'], true)) {
            $warnings[] = 'ModemManager is running. It sends modem commands to new serial ports and can upset the SIM800L. Stop it: sudo systemctl disable --now ModemManager';
        }
        return $warnings;
    }

    private function unitState(string $unit): string
    {
        if (PHP_OS_FAMILY !== 'Linux') {
            return 'unknown';
        }
        $p = new \Symfony\Component\Process\Process(['systemctl', 'is-active', $unit]);
        $p->setTimeout(5);
        $p->run();
        return trim($p->getOutput()) ?: 'unknown';
    }

    /** KEY=value pairs from scripts/.env (plain values only). */
    private function readScriptsEnv(): array
    {
        $vars = [];
        $path = config('database.ems_env_path');
        foreach (preg_split('/\r\n|\r|\n/', (string) @file_get_contents($path)) as $line) {
            if (preg_match('/^\s*([A-Z0-9_]+)\s*=\s*(.*?)\s*$/', $line, $m)) {
                $vars[$m[1]] = trim(preg_replace('/\s+#.*$/', '', $m[2]), "\"'");
            }
        }
        return $vars;
    }

    /** Sets keys in scripts/.env, replacing existing lines or appending. Returns an error or null. */
    private function writeScriptsEnv(array $values): ?string
    {
        $path = config('database.ems_env_path');
        $text = @file_get_contents($path);
        if ($text === false) {
            return "Can't read {$path}.";
        }
        foreach ($values as $key => $value) {
            $line = "{$key}={$value}";
            $text = preg_match("/^{$key}=.*$/m", $text)
                ? preg_replace("/^{$key}=.*$/m", str_replace('$', '\$', $line), $text, 1)
                : rtrim($text, "\r\n") . PHP_EOL . $line . PHP_EOL;
        }
        if (@file_put_contents($path, $text, LOCK_EX) === false) {
            return "Can't write {$path} (permissions).";
        }
        return null;
    }
}