<?php

namespace App\Http\Controllers;

use App\Models\SeismicStation;
use App\Models\Station;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;

class DashboardController extends Controller
{
    public function index()
    {
        [$airQualityData, $seismicData] = $this->buildDashboardData();
        $systemSummary = $this->buildSystemSummary();

        return view('index', compact('airQualityData', 'seismicData', 'systemSummary'));
    }

    /**
     * JSON endpoint used by the dashboard's AJAX polling (see index.blade.php).
     * Returns everything the view needs to refresh in place: station tables,
     * status counts, and system health tiles — without a full page reload.
     */
    public function data()
    {
        [$airQualityData, $seismicData] = $this->buildDashboardData();

        $idleThresholdMinutes    = 2;
        $offlineThresholdMinutes = 3;

        $airQualityCounts = $this->annotateStatus($airQualityData, $idleThresholdMinutes, $offlineThresholdMinutes);
        $seismicCounts    = $this->annotateStatus($seismicData, $idleThresholdMinutes, $offlineThresholdMinutes);

        return response()->json([
            'airQualityData'   => $airQualityData,
            'seismicData'      => $seismicData,
            'airQualityCounts' => $airQualityCounts,
            'seismicCounts'    => $seismicCounts,
            'systemHealth'     => $this->buildSystemHealth(),
            'systemSummary'    => $this->buildSystemSummary(),
            'generatedAt'      => now()->timezone('Asia/Manila')->format('Y-m-d h:i A'),
        ]);
    }

    /**
     * Generates a point-in-time PDF snapshot of system status: air quality
     * and seismic station tables (location, status, total readings), plus
     * who generated it and when. Mirrors the same online/idle/offline
     * thresholds used on the live dashboard so the numbers on the PDF match
     * what the user was looking at when they clicked the button.
     */
    public function generateReport(Request $request)
    {
        [$airQualityData, $seismicData] = $this->buildDashboardData();

        $idleThresholdMinutes    = 2;
        $offlineThresholdMinutes = 3;

        $airQualityCounts = $this->annotateStatus($airQualityData, $idleThresholdMinutes, $offlineThresholdMinutes);
        $seismicCounts    = $this->annotateStatus($seismicData, $idleThresholdMinutes, $offlineThresholdMinutes);

        $generatedAt = now()->timezone('Asia/Manila');

        // $request->user() only resolves if the auth guard actually
        // populated the request — this app doesn't always rely on that
        // (see ServicesController::action(), which falls back to
        // session('username') for the exact same reason). Match that
        // pattern here instead of defaulting straight to 'Unknown user'.
        $generatedBy = optional(Auth::user())->name
            ?? optional(Auth::user())->username
            ?? session('username')
            ?? 'Unknown user';

        $health = $this->buildSystemHealth();

        $uptimeParts = [];
        if ($health['uptime']['days'] > 0)  $uptimeParts[] = $health['uptime']['days'] . 'd';
        if ($health['uptime']['hours'] > 0) $uptimeParts[] = $health['uptime']['hours'] . 'h';
        $uptimeParts[]     = $health['uptime']['minutes'] . 'm';
        $systemUptimeHuman = implode(' ', $uptimeParts);

        // buildSystemHealth()'s disk.percent is "% used" (matches the live
        // dashboard's red/amber/green bars, high = bad). The PDF report's
        // Good/Warning/Critical bands run the other direction (high =
        // good), so what we hand the report is the inverse: % of the disk
        // that's still free.
        $storageTotalGb = round($health['disk']['total_bytes'] / (1024 ** 3), 1);
        $storageUsedGb  = round($health['disk']['used_bytes'] / (1024 ** 3), 1);
        $storagePercent = $health['disk']['total_bytes']
            ? round(100 - $health['disk']['percent'], 1)
            : null;

        $mqtt     = $this->checkUnitStatus('mosquitto.service');
        $database = $this->checkUnitStatus('postgresql.service');
        $ems      = $this->checkUnitStatus('ems.target');

        $pdf = Pdf::loadView('reports.system-status', [
            'airQualityData'   => $airQualityData,
            'seismicData'      => $seismicData,
            'airQualityCounts' => $airQualityCounts,
            'seismicCounts'    => $seismicCounts,
            'generatedAt'      => $generatedAt,
            'generatedBy'      => $generatedBy,

            'systemUptimeHuman' => $systemUptimeHuman,
            'storagePercent'    => $storagePercent,
            'storageUsedGb'     => $storageUsedGb,
            'storageTotalGb'    => $storageTotalGb,

            'mqttOnline'         => $mqtt['running'],
            'mqttStatusText'     => $mqtt['active'],
            'databaseOnline'     => $database['running'],
            'databaseStatusText' => $database['active'],
            'emsOnline'          => $ems['running'],
            'emsStatusText'      => $ems['active'],
        ])->setPaper('a4', 'portrait');

        $filename = 'system-status-report-' . $generatedAt->format('Y-m-d_His') . '.pdf';

        return $pdf->download($filename);
    }

    /**
     * The aq/seismic database connections store data_time/time as naive
     * UTC (no offset in the string), while the server/app displays in
     * Asia/Manila (UTC+8). Left unconverted, the dashboard's "Latest"
     * column silently showed the raw UTC value as if it were already
     * local time — e.g. reading 05:31 AM when the server clock actually
     * read 01:28 PM. This normalizes every installed_at/latest_at value
     * to a Manila-local string as soon as it leaves the DB, so both the
     * server-rendered first load and the JSON used by AJAX/PDF are
     * already correct — no timezone math left for the blade/JS to get
     * wrong.
     */
    private function toManila($rawUtcTimestamp): ?string
    {
        if (empty($rawUtcTimestamp)) {
            return null;
        }

        return \Carbon\Carbon::parse($rawUtcTimestamp, 'UTC')
            ->timezone('Asia/Manila')
            ->format('Y-m-d H:i:s');
    }

    /**
     * Shared query/merge logic for both the live dashboard and the PDF
     * report, so the two never drift out of sync.
     *
     * @return array{0: \Illuminate\Support\Collection, 1: \Illuminate\Support\Collection}
     */
    private function buildDashboardData(): array
    {
        // ---------- Air Quality ----------
        $stations = Station::orderBy('station_mn')->get();

        // Grouping by station_mn ALONE (not station_mn + ip_address) is
        // deliberate: if a station's ip_address ever changes in sensor_data
        // (DHCP reassignment, reconnect, etc.), grouping by both columns
        // splits one station's readings into two groups, and the keyBy()
        // below would silently keep only one of them — freezing
        // installed_at/latest_at even while the other group keeps growing.
        $aqReadings = DB::connection('aq')
            ->table('sensor_data')
            ->select(
                'station_mn',
                DB::raw('MIN(data_time) as installed_at'),
                DB::raw('MAX(data_time) as latest_at'),
                DB::raw('COUNT(*) as total')
            )
            ->groupBy('station_mn')
            ->get()
            ->keyBy('station_mn');

        // IP is fetched separately, tied to whichever row actually has the
        // max data_time per station, so it doesn't affect the aggregation
        // above and always reflects the most recent reading.
        $latestIps = collect(DB::connection('aq')->select("
                SELECT s1.station_mn, s1.ip_address AS ip
                FROM sensor_data s1
                INNER JOIN (
                    SELECT station_mn, MAX(data_time) AS max_time
                    FROM sensor_data
                    GROUP BY station_mn
                ) s2 ON s1.station_mn = s2.station_mn AND s1.data_time = s2.max_time
            "))
            ->keyBy('station_mn');

        $airQualityData = $stations
            ->map(function ($station) use ($aqReadings, $latestIps) {
                $reading = $aqReadings->get($station->station_mn);
                $ip      = $latestIps->get($station->station_mn)->ip ?? null;

                return (object) [
                    'station_mn'   => $station->station_mn,
                    'station'      => $station->station_name ?: $station->station_mn,
                    'ip'           => $ip ?? $station->lead_ip,
                    // TODO: swap for a real location/address column on the
                    // stations table if one exists — currently falling
                    // back to the IP, same as the rest of the dashboard.
                    'location'     => $station->location ?? $station->lead_ip ?? '—',
                    'installed_at' => $this->toManila($reading->installed_at ?? null),
                    'latest_at'    => $this->toManila($reading->latest_at ?? null),
                    'total'        => $reading->total ?? 0,
                ];
            })
            ->sortByDesc('total')
            ->values();

        // ---------- Seismic ----------
        $this->syncNewSeismicStations();

        $seismicStations = SeismicStation::orderBy('station_id')->get();

        // Grouped by station_id alone — same reasoning as the air quality
        // query above. station_name is never read off $reading downstream
        // (the display uses $station->station_name from the local
        // seismic_stations registry instead), so it's safe to drop here.
        $seismicReadings = DB::connection('seismic')
            ->table('station_metrics')
            ->select(
                'station_id',
                DB::raw('MIN(time) as installed_at'),
                DB::raw('MAX(time) as latest_at'),
                DB::raw('COUNT(*) as total')
            )
            ->groupBy('station_id')
            ->get()
            ->keyBy('station_id');

        $seismicData = $seismicStations
            ->map(function ($station) use ($seismicReadings) {
                $reading = $seismicReadings->get($station->station_id);

                return (object) [
                    'station_id'   => $station->station_id,
                    'station'      => $station->station_name ?: $station->station_id,
                    'ip'           => $station->station_id,
                    'location'     => ($station->latitude !== null && $station->longitude !== null)
                        ? number_format((float) $station->latitude, 4) . ', ' . number_format((float) $station->longitude, 4)
                        : '—',
                    'installed_at' => $this->toManila($reading->installed_at ?? null),
                    'latest_at'    => $this->toManila($reading->latest_at ?? null),
                    'total'        => $reading->total ?? 0,
                ];
            })
            ->sortByDesc('total')
            ->values();

        return [$airQualityData, $seismicData];
    }

    /**
     * Reads CPU / Memory / Storage / Uptime from /proc and disk_*_space().
     * Same logic that used to live inline in index.blade.php — pulled out
     * here so the AJAX data() endpoint can refresh these tiles too instead
     * of only the station tables. Falls back to zeros/"—" on platforms
     * without /proc (e.g. shared hosting, Windows) exactly as before.
     *
     * @return array{cpu: array, memory: array, disk: array, uptime: array}
     */
    private function buildSystemHealth(): array
    {
        $barColor = function ($percent) {
            if ($percent >= 85) return ['text' => 'text-red-400', 'bar' => 'bg-red-500'];
            if ($percent >= 60) return ['text' => 'text-amber-400', 'bar' => 'bg-amber-500'];
            return ['text' => 'text-munti-green-400', 'bar' => 'bg-munti-green-500'];
        };

        // Storage (root filesystem)
        $diskTotal   = @disk_total_space('/') ?: 0;
        $diskFree    = @disk_free_space('/') ?: 0;
        $diskUsed    = $diskTotal ? $diskTotal - $diskFree : 0;
        $diskPercent = $diskTotal ? round(($diskUsed / $diskTotal) * 100, 1) : 0;

        // Memory
        $memTotal = 0;
        $memAvailable = 0;
        if (@is_readable('/proc/meminfo')) {
            foreach (file('/proc/meminfo') as $line) {
                if (str_starts_with($line, 'MemTotal:')) {
                    $memTotal = (int) filter_var($line, FILTER_SANITIZE_NUMBER_INT) * 1024;
                }
                if (str_starts_with($line, 'MemAvailable:')) {
                    $memAvailable = (int) filter_var($line, FILTER_SANITIZE_NUMBER_INT) * 1024;
                }
            }
        }
        $memUsed    = $memTotal ? $memTotal - $memAvailable : 0;
        $memPercent = $memTotal ? round(($memUsed / $memTotal) * 100, 1) : 0;

        // CPU (approximated from 1-minute load average / core count)
        $cpuCores = 1;
        if (@is_readable('/proc/cpuinfo')) {
            $cpuCores = max(1, substr_count(file_get_contents('/proc/cpuinfo'), 'processor'));
        }
        $load = function_exists('sys_getloadavg') ? sys_getloadavg() : false;
        $load = $load ?: [0, 0, 0];
        $cpuPercent = round(min(($load[0] / $cpuCores) * 100, 100), 1);

        // Uptime
        $uptimeSeconds = 0;
        if (@is_readable('/proc/uptime')) {
            $uptimeSeconds = (int) floatval(explode(' ', file_get_contents('/proc/uptime'))[0]);
        }

        return [
            'cpu' => [
                'percent' => $cpuPercent,
                'cores'   => $cpuCores,
                'load'    => round($load[0], 2),
                'colors'  => $barColor($cpuPercent),
            ],
            'memory' => [
                'percent' => $memPercent,
                'used'    => $this->formatBytes($memUsed),
                'total'   => $this->formatBytes($memTotal),
                'colors'  => $barColor($memPercent),
            ],
            'disk' => [
                'percent'     => $diskPercent,
                'used'        => $this->formatBytes($diskUsed),
                'total'       => $this->formatBytes($diskTotal),
                // Raw bytes alongside the formatted strings above, so
                // generateReport() can compute a precise GB figure for the
                // PDF without re-parsing "120.5 GB" back into a number.
                'used_bytes'  => $diskUsed,
                'total_bytes' => $diskTotal,
                'colors'      => $barColor($diskPercent),
            ],
            'uptime' => [
                'days'    => intdiv($uptimeSeconds, 86400),
                'hours'   => intdiv($uptimeSeconds % 86400, 3600),
                'minutes' => intdiv($uptimeSeconds % 3600, 60),
            ],
        ];
    }

    /**
     * Hardware/OS identity info for the "System Summary" tile — device
     * model, CPU model, OS version, DIMM count, storage type, network
     * ports. Unlike buildSystemHealth() (live percentages, re-read every
     * poll), this is essentially static between reboots, so it's cached
     * for 5 minutes rather than re-run on every request. The 5 minute
     * window is short enough that a NIC being unplugged still shows up
     * promptly, but long enough to avoid shelling out to dmidecode/lsblk
     * on every AJAX poll.
     *
     * DIMM detail requires dmidecode, which needs root. If the web
     * server user doesn't have passwordless sudo for it, memory falls
     * back to just the total RAM (from /proc/meminfo, no root needed)
     * with a note — it never breaks the rest of the tile. To enable full
     * DIMM detail, add a sudoers rule for whatever user PHP-FPM/Apache
     * runs as, e.g.:
     *   echo 'www-data ALL=(root) NOPASSWD: /usr/sbin/dmidecode' | sudo tee /etc/sudoers.d/dashboard-dmidecode
     *
     * @return array{device_model: string, cpu_model: string, os_version: string, memory: array, storage: string, network: array}
     */
    private function buildSystemSummary(): array
    {
        return Cache::remember('dashboard.system_summary', 300, function () {
            return [
                'device_model' => $this->detectDeviceModel(),
                'cpu_model'    => $this->detectCpuModel(),
                'os_version'   => $this->detectOsVersion(),
                'memory'       => $this->detectMemoryDimms(),
                'storage'      => $this->detectStorageType(),
                'network'      => $this->detectNetworkPorts(),
            ];
        });
    }

    /**
     * Raspberry Pi and most ARM SBCs expose the board model via the
     * device tree — world-readable, no root needed. Standard x86
     * servers/desktops expose it via DMI sysfs entries instead, which
     * (unlike the dmidecode binary) are also world-readable on almost
     * every distro.
     */
    private function detectDeviceModel(): string
    {
        if (@is_readable('/proc/device-tree/model')) {
            $model = trim(str_replace("\0", '', file_get_contents('/proc/device-tree/model')));
            if ($model !== '') {
                return $model;
            }
        }

        $vendor = @is_readable('/sys/devices/virtual/dmi/id/sys_vendor')
            ? trim(file_get_contents('/sys/devices/virtual/dmi/id/sys_vendor')) : '';
        $product = @is_readable('/sys/devices/virtual/dmi/id/product_name')
            ? trim(file_get_contents('/sys/devices/virtual/dmi/id/product_name')) : '';
        $combined = trim("$vendor $product");

        return $combined !== '' ? $combined : 'Unknown Device';
    }

    /**
     * lscpu (util-linux, present on virtually every distro incl.
     * Raspberry Pi OS) is far more reliable across architectures than
     * hand-parsing /proc/cpuinfo: on ARM it decodes the CPU
     * implementer/part fields against a known-core database (e.g.
     * "Cortex-A72") even when /proc/cpuinfo itself has no "model name"
     * line, which is exactly the case on Raspberry Pi OS kernels since
     * Bookworm — they dropped the old "Hardware" field in favor of a
     * plain "Model" line. /proc/cpuinfo is kept as a fallback (with
     * every field name different kernels have used for this) in case
     * lscpu isn't installed.
     */
    private function detectCpuModel(): string
    {
        $lscpu = new Process(['lscpu']);
        $lscpu->setTimeout(5);
        $lscpu->run();
        if ($lscpu->isSuccessful() && preg_match('/^\s*Model name:\s*(.+)$/mi', $lscpu->getOutput(), $match)) {
            $model = trim($match[1]);
            if ($model !== '') {
                return $model;
            }
        }

        if (@is_readable('/proc/cpuinfo')) {
            $cpuinfo = file_get_contents('/proc/cpuinfo');
            foreach (['model name', 'Model', 'Hardware', 'cpu model'] as $field) {
                if (preg_match('/^' . preg_quote($field, '/') . '\s*:\s*(.+)$/mi', $cpuinfo, $match)) {
                    $value = trim($match[1]);
                    if ($value !== '') {
                        return $value;
                    }
                }
            }
        }

        return 'Unknown CPU';
    }

    private function detectOsVersion(): string
    {
        $pretty = null;

        if (@is_readable('/etc/os-release')) {
            foreach (file('/etc/os-release') as $line) {
                if (str_starts_with($line, 'PRETTY_NAME=')) {
                    $pretty = trim(explode('=', $line, 2)[1], " \t\n\r\0\x0B\"");
                    break;
                }
            }
        }

        $kernel = php_uname('r');

        return ($pretty ?: 'Unknown OS') . ($kernel ? " (kernel {$kernel})" : '');
    }

    /**
     * Same MemTotal parse buildSystemHealth() does — duplicated rather
     * than shared so this method still works (with a total-RAM-only
     * answer) even in contexts where buildSystemHealth() isn't called.
     */
    private function readMemTotalBytes(): int
    {
        $memTotal = 0;
        if (@is_readable('/proc/meminfo')) {
            foreach (file('/proc/meminfo') as $line) {
                if (str_starts_with($line, 'MemTotal:')) {
                    $memTotal = (int) filter_var($line, FILTER_SANITIZE_NUMBER_INT) * 1024;
                    break;
                }
            }
        }
        return $memTotal;
    }

    /**
     * DIMM-level detail (slot count, size, type, speed per stick) needs
     * dmidecode, which needs root. `sudo -n` fails immediately instead
     * of hanging on a password prompt if the sudoers rule (see
     * buildSystemSummary() docblock) hasn't been set up — in that case
     * we still return the total RAM figure, just without slot detail.
     */
    private function detectMemoryDimms(): array
    {
        $totalLabel = $this->formatBytes($this->readMemTotalBytes());

        $process = new Process(['sudo', '-n', 'dmidecode', '-t', 'memory']);
        $process->setTimeout(5);
        $process->run();

        if (!$process->isSuccessful()) {
            return [
                'available'   => false,
                'slots_used'  => null,
                'slots_total' => null,
                'sticks'      => [],
                'total_label' => $totalLabel,
                'note'        => 'DIMM detail requires dmidecode + sudo access',
            ];
        }

        // dmidecode separates each record with a blank line; "Memory
        // Device" (DMI type 17) is one physical slot, populated or not —
        // distinct from "Physical Memory Array" (type 16), which
        // describes the slot bank as a whole and isn't per-stick.
        $records    = preg_split('/\n\s*\n/', $process->getOutput());
        $sticks     = [];
        $slotsTotal = 0;

        foreach ($records as $record) {
            if (!str_contains($record, 'Memory Device')) {
                continue;
            }
            $slotsTotal++;

            if (preg_match('/Size:\s*(.+)/', $record, $sizeMatch) && !str_contains($sizeMatch[1], 'No Module Installed')) {
                preg_match('/Type:\s*(.+)/', $record, $typeMatch);
                preg_match('/Speed:\s*(.+)/', $record, $speedMatch);
                preg_match('/Locator:\s*(.+)/', $record, $locatorMatch);

                $sticks[] = [
                    'locator' => trim($locatorMatch[1] ?? '—'),
                    'size'    => trim($sizeMatch[1]),
                    'type'    => trim($typeMatch[1] ?? '—'),
                    'speed'   => trim($speedMatch[1] ?? '—'),
                ];
            }
        }

        return [
            'available'   => true,
            'slots_used'  => count($sticks),
            'slots_total' => $slotsTotal,
            'sticks'      => $sticks,
            'total_label' => $totalLabel,
            'note'        => null,
        ];
    }

    /**
     * Resolves the block device backing the root filesystem, then reads
     * whether it's rotational and what transport it's on to label it
     * (e.g. "SSD NVMe", "HDD SATA", "SSD eMMC/SD"). Uses lsblk's PKNAME
     * to walk a partition back to its parent disk rather than
     * hand-rolling a regex for every naming scheme (sda2, nvme0n1p1,
     * mmcblk0p2 all differ).
     */
    private function detectStorageType(): string
    {
        $findmnt = new Process(['findmnt', '-no', 'SOURCE', '/']);
        $findmnt->setTimeout(5);
        $findmnt->run();
        $source = trim($findmnt->getOutput());

        if ($source === '') {
            return 'Unknown';
        }

        $parentLookup = new Process(['lsblk', '-no', 'PKNAME', $source]);
        $parentLookup->setTimeout(5);
        $parentLookup->run();
        $parent = trim($parentLookup->getOutput());
        // No parent means root sits directly on a whole disk with no
        // partition table — the source itself is already the disk.
        $disk = $parent !== '' ? $parent : basename($source);

        $diskInfo = new Process(['lsblk', '-dno', 'ROTA,TRAN', "/dev/{$disk}"]);
        $diskInfo->setTimeout(5);
        $diskInfo->run();
        [$rotational, $transport] = array_pad(preg_split('/\s+/', trim($diskInfo->getOutput())), 2, '');

        $transportLabel = match (true) {
            $transport === 'nvme' => 'NVMe',
            $transport === 'sata' => 'SATA',
            $transport === 'usb'  => 'USB',
            $transport === 'mmc' || str_starts_with($disk, 'mmcblk') => 'eMMC/SD',
            $transport !== ''     => strtoupper($transport),
            default => '',
        };
        $mediaLabel = $rotational === '0' ? 'SSD' : ($rotational === '1' ? 'HDD' : '');

        return trim("$mediaLabel $transportLabel") ?: 'Unknown';
    }

    /**
     * Lists physical network interfaces (skips loopback and virtual
     * interfaces like docker bridges/veth pairs, identified by the
     * absence of a /sys/class/net/<iface>/device link back to real
     * hardware) with link state and speed. Speed is only read while a
     * link is up — sysfs returns garbage for a down interface's speed.
     */
    private function detectNetworkPorts(): array
    {
        $ports = [];
        $interfaces = @scandir('/sys/class/net') ?: [];

        foreach ($interfaces as $iface) {
            if ($iface === '.' || $iface === '..' || $iface === 'lo') {
                continue;
            }
            if (!@file_exists("/sys/class/net/{$iface}/device")) {
                continue;
            }

            $operstate = @is_readable("/sys/class/net/{$iface}/operstate")
                ? trim(file_get_contents("/sys/class/net/{$iface}/operstate"))
                : 'unknown';

            $speed = null;
            if ($operstate === 'up' && @is_readable("/sys/class/net/{$iface}/speed")) {
                $speedMbps = (int) trim(@file_get_contents("/sys/class/net/{$iface}/speed"));
                if ($speedMbps > 0) {
                    $speed = $speedMbps >= 1000
                        ? round($speedMbps / 1000, 1) . ' Gbps'
                        : $speedMbps . ' Mbps';
                }
            }

            $ports[] = [
                'name'   => $iface,
                'status' => $operstate,
                'speed'  => $speed,
            ];
        }

        return $ports;
    }

    /**
     * Duplicated (deliberately, in miniature) from ServicesController's
     * runQuiet()/statusFor() rather than shared — this report only needs
     * a running/stopped bool plus the raw `systemctl is-active` word for
     * three fixed units, not the enabled-state or start/stop/restart
     * machinery ServicesController owns for the managed-services page.
     * Same rule MaintenanceController already follows for its nmcli
     * duplication: pull this into a shared trait/service if a third
     * consumer needs the same check.
     */
    private function checkUnitStatus(string $unit): array
    {
        $process = new Process(['systemctl', 'is-active', $unit]);
        $process->setTimeout(5);
        // is-active exits non-zero for inactive/failed units — expected,
        // stdout ("inactive", "failed", etc.) is still what we want.
        $process->run();
        $active = trim($process->getOutput()) ?: 'unknown';

        return [
            'active'  => $active,
            'running' => $active === 'active',
        ];
    }

    /**
     * Same byte-formatting helper previously inline in the blade view.
     */
    private function formatBytes($bytes, int $decimals = 1): string
    {
        if (!$bytes) return '0 GB';
        $units  = ['B', 'KB', 'MB', 'GB', 'TB'];
        $factor = (int) floor((strlen((string) (int) $bytes) - 1) / 3);
        $factor = max(0, min($factor, count($units) - 1));
        return sprintf("%.{$decimals}f", $bytes / (1024 ** $factor)) . ' ' . $units[$factor];
    }

    /**
     * Attaches an ->status ('online'|'idle'|'offline') to every item in the
     * collection based on how long ago its last reading came in, and
     * returns the tallies. Same thresholds as the dashboard blade view.
     */
    private function annotateStatus($collection, int $idleThresholdMinutes, int $offlineThresholdMinutes): array
    {
        $counts = ['online' => 0, 'idle' => 0, 'offline' => 0];

        foreach ($collection as $item) {
            $status = 'offline';
            if (!empty($item->latest_at)) {
                // $item->latest_at has already been normalized to an
                // Asia/Manila-local string by toManila() in
                // buildDashboardData(), so it must be parsed with that
                // timezone explicitly — parsing without it would fall
                // back to the app's default timezone and reintroduce an
                // 8-hour skew into the online/idle/offline calculation.
                $minutesAgo = \Carbon\Carbon::parse($item->latest_at, 'Asia/Manila')->diffInMinutes(now());
                if ($minutesAgo <= $idleThresholdMinutes) {
                    $status = 'online';
                } elseif ($minutesAgo <= $offlineThresholdMinutes) {
                    $status = 'idle';
                }
            }
            $item->status = $status;
            $counts[$status]++;
        }

        return $counts;
    }

    /**
     * Auto-register any seismic station that's already reporting readings
     * into station_metrics (on the 'seismic' connection) but doesn't yet
     * have a row in the local seismic_stations registry — i.e. a station
     * that just came online for the first time. We pull its most recent
     * reading to seed station_name/latitude/longitude/elevation_m so the
     * new registry row isn't blank. This never touches stations that are
     * already registered.
     */
    private function syncNewSeismicStations(): void
    {
        $registeredIds = SeismicStation::pluck('station_id')->all();

        $unregisteredIds = DB::connection('seismic')
            ->table('station_metrics')
            ->select('station_id')
            ->whereNotIn('station_id', $registeredIds ?: [''])
            ->distinct()
            ->pluck('station_id');

        foreach ($unregisteredIds as $stationId) {
            $latest = DB::connection('seismic')
                ->table('station_metrics')
                ->where('station_id', $stationId)
                ->orderByDesc('time')
                ->first();

            try {
                SeismicStation::firstOrCreate(
                    ['station_id' => $stationId],
                    [
                        'station_name' => $latest->station_name ?? null,
                        'enabled'      => true,
                        'latitude'     => $latest->latitude ?? null,
                        'longitude'    => $latest->longitude ?? null,
                        'elevation_m'  => $latest->elevation_m ?? null,
                    ]
                );
            } catch (\Illuminate\Database\QueryException $e) {
                // Another concurrent request (e.g. a second open dashboard
                // tab auto-refreshing at the same moment) already inserted
                // this station first — safe to ignore.
            }
        }
    }
}