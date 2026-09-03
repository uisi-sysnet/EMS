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
    // Fixed canvas for the JPEG export (generateImageReport). Unlike the
    // PDF, this can't grow to fit content, so the station tables are
    // capped and a "+N more" note is shown when a list runs past that.
    private const IMAGE_WIDTH          = 1240;
    private const IMAGE_HEIGHT         = 1754;
    private const IMAGE_MARGIN         = 48;
    private const IMAGE_MAX_TABLE_ROWS = 999;  // deliberately high so the PDF and live dashboard can show the full list, while the JPEG is capped to IMAGE_MAX_PORT_ROWS below
    private const IMAGE_MAX_PORT_ROWS  = 5;

    public function index()
    {
        [$airQualityData, $seismicData] = $this->buildDashboardData();
        $systemSummary = $this->buildSystemSummary();
        $cameraCounts = $this->getCameraStatusCounts(); 

        return view('index', compact('airQualityData', 'seismicData', 'systemSummary', 'cameraCounts'));
    }

    /**
     * Snapshot of station statuses and system health for Telegram
     * notifications (daily digest + real-time alerts). Deliberately
     * reuses the exact same helpers as the live dashboard and JSON
     * polling endpoint (buildDashboardData, annotateStatus,
     * buildSystemHealth) so a station or health metric can never read
     * differently in a Telegram message than it does on-screen — the
     * storage-threshold mismatch between the PDF/JPEG reports earlier is
     * exactly the kind of drift this avoids.
     */
    public function telegramSnapshot(): array
    {
        [$airQualityData, $seismicData] = $this->buildDashboardData();

        // Same thresholds as data()'s AJAX polling endpoint.
        $idleThresholdMinutes    = 2;
        $offlineThresholdMinutes = 3;

        $airQualityCounts = $this->annotateStatus($airQualityData, $idleThresholdMinutes, $offlineThresholdMinutes);
        $seismicCounts    = $this->annotateStatus($seismicData, $idleThresholdMinutes, $offlineThresholdMinutes);
        $cameraCounts     = $this->getCameraStatusCounts();

        $health = $this->buildSystemHealth();

        // buildSystemHealth()'s disk.percent is % USED; storageStatusKey
        // expects % FREE (see its doc comment), so invert here exactly
        // like buildReportContext() does for the PDF/JPEG reports.
        $storagePercentFree = round(100 - $health['disk']['percent'], 1);

        return [
            'airQualityData'   => $airQualityData,   // Collection, each item already has ->status set
            'seismicData'      => $seismicData,
            'airQualityCounts' => $airQualityCounts, // ['online'=>n,'idle'=>n,'offline'=>n]
            'seismicCounts'    => $seismicCounts,
            'cameraCounts'     => $cameraCounts, 
            'health' => [
                'cpu' => [
                    'percent' => $health['cpu']['percent'],
                    'status'  => $this->usageStatusKey($health['cpu']['percent']),
                ],
                'memory' => [
                    'percent' => $health['memory']['percent'],
                    'status'  => $this->usageStatusKey($health['memory']['percent']),
                ],
                'storage' => [
                    'percent_free' => $storagePercentFree,
                    'status'       => $this->storageStatusKey($storagePercentFree),
                ],
            ],
            'generatedAt' => now()->timezone('Asia/Manila')->format('M j, Y g:i A'),
        ];
    }

    /**
     * Get camera status counts based on last_status from the database
     * - last_status = 'online' -> Online
     * - last_status = 'error' -> Offline
     * - enabled = false -> Offline
     * - no last_status set and enabled = true -> Online (default)
     */
    private function getCameraStatusCounts(): array
    {
        $cameras = \App\Models\Camera::all();
        
        $counts = ['online' => 0, 'idle' => 0, 'offline' => 0];
        
        foreach ($cameras as $camera) {
            // Disabled cameras are always offline
            if (!$camera->enabled) {
                $counts['offline']++;
                continue;
            }
            
            // Use the last_status from the database
            if ($camera->last_status === 'online') {
                $counts['online']++;
            } elseif ($camera->last_status === 'error') {
                $counts['offline']++;
            } else {
                // If no status set but enabled, consider it online
                // (or you could default to offline if you prefer)
                $counts['online']++;
            }
        }
        
        return $counts;
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
        $cameraCounts     = $this->getCameraStatusCounts();

        return response()->json([
            'airQualityData'   => $airQualityData,
            'seismicData'      => $seismicData,
            'airQualityCounts' => $airQualityCounts,
            'seismicCounts'    => $seismicCounts,
            'cameraCounts'     => $cameraCounts,
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
        $ctx = $this->buildReportContext();

        $pdf = Pdf::loadView('reports.system-status', $ctx)->setPaper('a4', 'portrait');

        $filename = 'system-status-report-' . $ctx['generatedAt']->format('Y-m-d_His') . '.pdf';

        return $pdf->download($filename);
    }

    /**
     * Same report as generateReport(), rendered as a flat 1920x1080 JPEG
     * instead of a PDF. Drawn directly with GD rather than a headless
     * browser/Node dependency, so it stays cheap to run on the same
     * low-resource boxes this app already targets (see
     * detectDeviceModel()'s Raspberry Pi device-tree check below). Reuses
     * the DejaVu Sans font dompdf already ships for the PDF report, so no
     * new font files are needed either.
     *
     * Unlike the PDF, the canvas is a fixed size rather than a scrolling
     * page, so the two station tables are capped to
     * IMAGE_MAX_TABLE_ROWS rows each (same total-readings sort
     * buildDashboardData() already applies) with a "+N more" note when a
     * list runs longer than that — see the PDF report for the full list.
     */
    public function generateImageReport(Request $request)
    {
        $ctx    = $this->buildReportContext();
        $pages  = $this->renderSystemStatusPages($ctx);   // returns array of GD resources

        $timestamp = $ctx['generatedAt']->format('Y-m-d_His');

        // Single page → just download the JPEG
        if (count($pages) === 1) {
            $filename = "system-status-report-{$timestamp}.jpg";

            return response()->streamDownload(function () use ($pages) {
                imagejpeg($pages[0], null, 90);
                imagedestroy($pages[0]);
            }, $filename, ['Content-Type' => 'image/jpeg']);
        }

        // Multiple pages → ZIP
        $zipFilename = "system-status-report-{$timestamp}.zip";
        $tmpZip = tempnam(sys_get_temp_dir(), 'report_');

        $zip = new \ZipArchive();
        $zip->open($tmpZip, \ZipArchive::OVERWRITE);

        foreach ($pages as $i => $img) {
            $pageNum = $i + 1;
            ob_start();
            imagejpeg($img, null, 90);
            $jpegData = ob_get_clean();
            imagedestroy($img);

            $zip->addFromString("page-{$pageNum}.jpg", $jpegData);
        }
        $zip->close();

        return response()->download($tmpZip, $zipFilename, [
            'Content-Type' => 'application/zip',
        ])->deleteFileAfterSend(true);
    }

    /**
     * Same image renderSystemStatusImage() draws for the "Download Image"
     * button, captured as raw JPEG bytes instead of streamed to the
     * browser. Used by the Telegram digest so what gets posted to chat is
     * the same report a user gets from that button — not a separate
     * text-only re-implementation that could drift from it.
     */
    public function buildReportImageJpeg(): string
    {
        $ctx   = $this->buildReportContext();
        $image = $this->renderSystemStatusImage($ctx);

        ob_start();
        imagejpeg($image, null, 90);
        $bytes = ob_get_clean();
        imagedestroy($image);

        return $bytes;
    }

    /**
     * Shared data-gathering for both the PDF (generateReport) and JPEG
     * (generateImageReport) exports, so the two can never drift out of
     * sync with each other. Field names match the reports.system-status
     * blade's variable names 1:1 so the PDF branch can hand the array
     * straight to the view.
     */
    private function buildReportContext(): array
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

        $health        = $this->buildSystemHealth();
        $systemSummary = $this->buildSystemSummary();

        // Same "X/Y DIMMs · total" vs "total (DIMM count needs sudo)"
        // formatting the dashboard's summary-memory tile uses — kept in
        // sync here rather than in the blade so the PDF/JPEG and live view
        // never phrase this differently.
        $memoryText = $systemSummary['memory']['available']
            ? $systemSummary['memory']['slots_used'] . '/' . $systemSummary['memory']['slots_total']
                . ' DIMMs · ' . $systemSummary['memory']['total_label']
            : $systemSummary['memory']['total_label'] . ' (DIMM count needs sudo)';

        $uptimeParts = [];
        if ($health['uptime']['days'] > 0)  $uptimeParts[] = $health['uptime']['days'] . 'd';
        if ($health['uptime']['hours'] > 0) $uptimeParts[] = $health['uptime']['hours'] . 'h';
        $uptimeParts[]     = $health['uptime']['minutes'] . 'm';
        $systemUptimeHuman = implode(' ', $uptimeParts);

        // buildSystemHealth()'s disk.percent is "% used" (matches the live
        // dashboard's red/amber/green bars, high = bad). The report's
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

        return [
            'airQualityData'   => $airQualityData,
            'seismicData'      => $seismicData,
            'airQualityCounts' => $airQualityCounts,
            'seismicCounts'    => $seismicCounts,
            'generatedAt'      => $generatedAt,
            'generatedBy'      => $generatedBy,

            // Hardware/OS identity — same buildSystemSummary() data the
            // live dashboard's "System Summary" tile shows. Exposed both
            // as the raw array (systemSummary) for blade sections that
            // read it directly the way index.blade.php does, and as
            // flattened keys below for the parts of the report that
            // predate that array (e.g. memoryText's DIMM-count phrasing).
            'systemSummary' => $systemSummary,
            'deviceModel'  => $systemSummary['device_model'],
            'cpuModel'     => $systemSummary['cpu_model'],
            'osVersion'    => $systemSummary['os_version'],
            'memoryText'   => $memoryText,
            'storageType'  => $systemSummary['storage'],
            'networkPorts' => $systemSummary['network']['ports'],

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
        ];
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
            $ports = $this->detectNetworkPorts();

            return [
                'device_model' => $this->detectDeviceModel(),
                'cpu_model'    => $this->detectCpuModel(),
                'os_version'   => $this->detectOsVersion(),
                'memory'       => $this->detectMemoryDimms(),
                'storage'      => $this->detectStorageType(),
                'network'      => [
                    'ports' => $ports,
                    'used'  => count(array_filter($ports, fn ($port) => $port['active'])),
                    'total' => count($ports),
                ],
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
     * hardware) with link state, speed, and IPv4 address in CIDR
     * notation. Speed is only read while a link is up — sysfs returns
     * garbage for a down interface's speed. Color/active are
     * precomputed here (rather than in the view) to match the rest of
     * this file's pattern of keeping status-color logic in one place:
     *   - green: link up and an IPv4 address is assigned
     *   - amber: link up but no address yet (e.g. still negotiating DHCP)
     *   - gray:  link down / cable unplugged
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

            $cidr   = $this->detectInterfaceCidr($iface);
            $active = $operstate === 'up';

            $ports[] = [
                'name'    => $iface,
                'status'  => $operstate,
                'speed'   => $speed,
                'ip_cidr' => $cidr,
                'active'  => $active,
                'colors'  => match (true) {
                    $active && $cidr !== null => ['bg' => 'bg-munti-green-500', 'text' => 'text-munti-green-400'],
                    $active                   => ['bg' => 'bg-amber-500', 'text' => 'text-amber-400'],
                    default                   => ['bg' => 'bg-surface-600', 'text' => 'text-text-500'],
                },
            ];
        }

        return $ports;
    }

    /**
     * Reads an interface's IPv4 address + prefix length and returns it
     * as CIDR notation (e.g. "192.168.1.42/24"). Prefers `ip -j` (JSON
     * output, iproute2 4.14+) for clean structured parsing; falls back
     * to scraping the plain-text output of the same command for older
     * iproute2 builds without -j support. Null if the interface has no
     * IPv4 address (down, or up but not yet configured).
     */
    private function detectInterfaceCidr(string $iface): ?string
    {
        $json = new Process(['ip', '-j', 'addr', 'show', $iface]);
        $json->setTimeout(5);
        $json->run();

        if ($json->isSuccessful()) {
            $data = json_decode($json->getOutput(), true);
            foreach ($data[0]['addr_info'] ?? [] as $addr) {
                if (($addr['family'] ?? null) === 'inet' && !empty($addr['local'])) {
                    return $addr['local'] . '/' . ($addr['prefixlen'] ?? 32);
                }
            }
            return null;
        }

        $text = new Process(['ip', 'addr', 'show', $iface]);
        $text->setTimeout(5);
        $text->run();
        if ($text->isSuccessful() && preg_match('/inet\s+(\d{1,3}(?:\.\d{1,3}){3}\/\d{1,2})/', $text->getOutput(), $match)) {
            return $match[1];
        }

        return null;
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

    // ------------------------------------------------------------------
    // JPEG export (generateImageReport) — GD drawing helpers.
    // Mirrors reports/system-status.blade.php's layout/colors as closely
    // as a fixed-size raster canvas allows.
    // ------------------------------------------------------------------

    /**
     * Renders the system-status report as one or more portrait pages.
     * Returns an array of GD image resources (caller must imagedestroy them).
     */
    private function renderSystemStatusPages(array $ctx): array
    {
        $pages = [];
        $page  = $this->createBlankPage();
        $y     = self::IMAGE_MARGIN;

        // ---- Header (only on first page) ----
        $y = $this->drawImgHeader($page, $ctx['generatedAt'], $ctx['generatedBy'],
                                self::IMAGE_MARGIN, self::IMAGE_WIDTH - 2 * self::IMAGE_MARGIN, $y);

        $y += 20;
        $y = $this->drawImgHardwareNetwork($page, $ctx, self::IMAGE_MARGIN,
                                        self::IMAGE_WIDTH - 2 * self::IMAGE_MARGIN, $y);

        // ---- Stations Status Summary ----
        $y = $this->ensureSpace($pages, $page, $y, 180);   // need ~180px
        $y += 20;
        $y = $this->drawImgSectionTitle($page, 'Stations Status Summary',
                                        self::IMAGE_MARGIN, self::IMAGE_WIDTH - 2 * self::IMAGE_MARGIN, $y);

        [$catW, $onlineW, $idleW, $offlineW, $totalW] = $this->imgColumnWidths(
            self::IMAGE_WIDTH - 2 * self::IMAGE_MARGIN, [0.32, 0.17, 0.17, 0.17, 0.17]
        );

        $y = $this->drawImgTable($page, self::IMAGE_MARGIN, $y, self::IMAGE_WIDTH - 2 * self::IMAGE_MARGIN,
            [
                ['label' => 'Category', 'width' => $catW],
                ['label' => 'Online',   'width' => $onlineW, 'align' => 'center'],
                ['label' => 'Idle',     'width' => $idleW,   'align' => 'center'],
                ['label' => 'Offline',  'width' => $offlineW,'align' => 'center'],
                ['label' => 'Total',    'width' => $totalW,  'align' => 'center'],
            ],
            [
                ['Air Quality', $ctx['airQualityCounts']['online'], $ctx['airQualityCounts']['idle'], $ctx['airQualityCounts']['offline'], $ctx['airQualityData']->count()],
                ['Seismic',     $ctx['seismicCounts']['online'],    $ctx['seismicCounts']['idle'],    $ctx['seismicCounts']['offline'],    $ctx['seismicData']->count()],
                [
                    'Total',
                    $ctx['airQualityCounts']['online'] + $ctx['seismicCounts']['online'],
                    $ctx['airQualityCounts']['idle']   + $ctx['seismicCounts']['idle'],
                    $ctx['airQualityCounts']['offline']+ $ctx['seismicCounts']['offline'],
                    $ctx['airQualityData']->count() + $ctx['seismicData']->count(),
                ],
            ]
        );

        // ---- System Status ----
        $y = $this->ensureSpace($pages, $page, $y, 220);
        $y += 20;
        $y = $this->drawImgSectionTitle($page, 'System Status',
                                        self::IMAGE_MARGIN, self::IMAGE_WIDTH - 2 * self::IMAGE_MARGIN, $y);

        $storageDetail = isset($ctx['storageUsedGb'], $ctx['storageTotalGb'])
            ? number_format($ctx['storageUsedGb'], 1) . ' GB used of ' . number_format($ctx['storageTotalGb'], 1) . ' GB'
            : '—';
        $storageValue = $ctx['storagePercent'] !== null ? number_format($ctx['storagePercent'], 2) . '% free' : '—';

        [$metricW, $detailW, $valueW, $statusW] = $this->imgColumnWidths(
            self::IMAGE_WIDTH - 2 * self::IMAGE_MARGIN, [0.22, 0.38, 0.20, 0.20]
        );

        $y = $this->drawImgTable($page, self::IMAGE_MARGIN, $y, self::IMAGE_WIDTH - 2 * self::IMAGE_MARGIN,
            [
                ['label' => 'Metric', 'width' => $metricW],
                ['label' => 'Detail', 'width' => $detailW],
                ['label' => 'Value',  'width' => $valueW],
                ['label' => 'Status', 'width' => $statusW],
            ],
            [
                ['System Uptime', 'Since last restart', $ctx['systemUptimeHuman'] ?: '—',
                ['badge' => true, 'label' => $this->boolStatusLabel(isset($ctx['systemUptimeHuman'])), 'status' => $this->boolStatusKey(isset($ctx['systemUptimeHuman']))]],
                ['Storage', $storageDetail, $storageValue,
                ['badge' => true, 'label' => $this->storageStatusLabel($ctx['storagePercent']), 'status' => $this->storageStatusKey($ctx['storagePercent'])]],
                ['MQTT Broker (Mosquitto)', 'mosquitto.service', $ctx['mqttStatusText'] ?? '—',
                ['badge' => true, 'label' => $this->boolStatusLabel($ctx['mqttOnline'] ?? null), 'status' => $this->boolStatusKey($ctx['mqttOnline'] ?? null)]],
                ['Database (PostgreSQL)', 'postgresql.service', $ctx['databaseStatusText'] ?? '—',
                ['badge' => true, 'label' => $this->boolStatusLabel($ctx['databaseOnline'] ?? null), 'status' => $this->boolStatusKey($ctx['databaseOnline'] ?? null)]],
                ['EMS Gateway', 'ems.target', $ctx['emsStatusText'] ?? '—',
                ['badge' => true, 'label' => $this->boolStatusLabel($ctx['emsOnline'] ?? null), 'status' => $this->boolStatusKey($ctx['emsOnline'] ?? null)]],
            ]
        );

        // ---- Station tables (side-by-side on first page, continue on next pages if needed) ----
        $y = $this->ensureSpace($pages, $page, $y, 400);
        $y += 24;

        $gutter    = 32;
        $halfWidth = (int) ((self::IMAGE_WIDTH - 2 * self::IMAGE_MARGIN - $gutter) / 2);
        $rightX    = self::IMAGE_MARGIN + $halfWidth + $gutter;

        // We still use the capped version for cleanliness, but you can remove the take() later
        $this->drawImgStationTable($page, 'Air Quality Stations', $ctx['airQualityData'], $ctx['airQualityCounts'],
                                self::IMAGE_MARGIN, $y, $halfWidth);
        $this->drawImgStationTable($page, 'Seismic Stations', $ctx['seismicData'], $ctx['seismicCounts'],
                                $rightX, $y, $halfWidth);

        // Footer on every page
        $this->drawImgFooter($page, self::IMAGE_MARGIN, self::IMAGE_WIDTH - 2 * self::IMAGE_MARGIN,
                            self::IMAGE_HEIGHT - self::IMAGE_MARGIN, $ctx['generatedAt']);

        $pages[] = $page;
        return $pages;
    }

    /** Create a blank white portrait page */
    private function createBlankPage()
    {
        $img = imagecreatetruecolor(self::IMAGE_WIDTH, self::IMAGE_HEIGHT);
        $white = imagecolorallocate($img, 255, 255, 255);
        imagefilledrectangle($img, 0, 0, self::IMAGE_WIDTH, self::IMAGE_HEIGHT, $white);
        return $img;
    }

    /**
     * If remaining space on current page is less than $needed, close the page
     * and start a new one. Returns the new $y (top margin on new page).
     */
    private function ensureSpace(array &$pages, &$page, int $y, int $needed): int
    {
        $available = self::IMAGE_HEIGHT - self::IMAGE_MARGIN - $y;
        if ($available >= $needed) {
            return $y;
        }

        // Finish current page
        $this->drawImgFooter($page, self::IMAGE_MARGIN, self::IMAGE_WIDTH - 2 * self::IMAGE_MARGIN,
                            self::IMAGE_HEIGHT - self::IMAGE_MARGIN, now()->timezone('Asia/Manila'));
        $pages[] = $page;

        // New page
        $page = $this->createBlankPage();
        return self::IMAGE_MARGIN + 20;   // small top margin on continuation pages
    }

    private function drawImgHeader($image, $generatedAt, string $generatedBy, int $x, int $width, int $y): int
    {
        $teal = [15, 118, 110];
        $gray = [136, 136, 136];
        $dark = [26, 26, 26];

        $titleBaseline = $y + 28;
        $this->imgText($image, 'Environment Monitoring System Status Report', $x, $titleBaseline, 24, $teal, true);

        $subtitleBaseline = $titleBaseline + 22;
        $this->imgText($image, 'Developed by Uplink Integrated Solutions Inc.', $x, $subtitleBaseline, 13, $gray);

        // Right-aligned report meta, roughly vertically centered against the title block.
        $this->imgText($image, 'Report Date/Time: ' . $generatedAt->format('F d, Y  h:i A'), $x + $width, $titleBaseline - 4, 14, $dark, false, 'right');
        $this->imgText($image, 'Generated By: ' . $generatedBy, $x + $width, $titleBaseline + 20, 14, $dark, false, 'right');

        $dividerY  = $subtitleBaseline + 14;
        $tealColor = imagecolorallocate($image, $teal[0], $teal[1], $teal[2]);
        imagefilledrectangle($image, $x, $dividerY, $x + $width, $dividerY + 3, $tealColor);

        return $dividerY + 3;
    }

    private function drawImgHardwareNetwork($image, array $ctx, int $x, int $width, int $y): int
    {
        $boxHeight = 190;
        $gutter    = 32;
        $halfWidth = (int) (($width - $gutter) / 2);
        $rightX    = $x + $halfWidth + $gutter;

        $this->drawImgBox($image, $x, $y, $halfWidth, $boxHeight);
        $this->drawImgBox($image, $rightX, $y, $halfWidth, $boxHeight);

        $labelColor = [119, 119, 119];
        $valueColor = [26, 26, 26];
        $pad        = 16;

        // Hardware box
        $rowY = $y + $pad + 12;
        $this->imgText($image, 'HARDWARE', $x + $pad, $rowY, 12, $labelColor, true);
        $rowY += 22;
        foreach ([
            ['Device Model', $ctx['deviceModel']],
            ['CPU Model', $ctx['cpuModel']],
            ['OS Version', $ctx['osVersion']],
            ['Memory', $ctx['memoryText']],
            ['Storage Type', $ctx['storageType']],
        ] as [$label, $value]) {
            $this->imgText($image, $label . ':', $x + $pad, $rowY, 13, $labelColor);
            $valueText = $this->imgTruncate((string) $value, $halfWidth - $pad - 170, 13);
            $this->imgText($image, $valueText, $x + $pad + 150, $rowY, 13, $valueColor);
            $rowY += 26;
        }

        // Network ports box
        $rowY = $y + $pad + 12;
        $this->imgText($image, 'NETWORK PORTS', $rightX + $pad, $rowY, 12, $labelColor, true);
        $rowY += 22;

        $ports = $ctx['networkPorts'];
        $shown = array_slice($ports, 0, self::IMAGE_MAX_PORT_ROWS);

        foreach ($shown as $port) {
            $active = $port['active'] ?? false;
            $ipCidr = $port['ip_cidr'] ?? null;
            $status = $active && $ipCidr ? 'online' : ($active ? 'idle' : 'offline');
            $label  = $active && $ipCidr ? 'Up' : ($active ? 'Up (No IP)' : 'Down');

            $this->imgText($image, (string) ($port['name'] ?? '—'), $rightX + $pad, $rowY, 13, $valueColor, true);
            $this->drawImgStatusBadge($image, $rightX + $pad + 90, $rowY - 14, $label, $status, 18);

            $detail     = trim(($ipCidr ?? '—') . (!empty($port['speed']) ? ' · ' . $port['speed'] : ''));
            $detailText = $this->imgTruncate($detail, $halfWidth - $pad - 220, 12);
            $this->imgText($image, $detailText, $rightX + $pad + 220, $rowY, 12, $labelColor);

            $rowY += 26;
        }

        if (empty($shown)) {
            $this->imgText($image, 'No network interfaces detected', $rightX + $pad, $rowY, 13, $labelColor);
        } elseif (count($ports) > self::IMAGE_MAX_PORT_ROWS) {
            $this->imgText($image, '+' . (count($ports) - self::IMAGE_MAX_PORT_ROWS) . ' more not shown', $rightX + $pad, $rowY, 11, $labelColor);
        }

        return $y + $boxHeight;
    }

    private function drawImgBox($image, int $x, int $y, int $width, int $height): void
    {
        $bg     = imagecolorallocate($image, 245, 247, 247);
        $border = imagecolorallocate($image, 221, 221, 221);
        imagefilledrectangle($image, $x, $y, $x + $width, $y + $height, $bg);
        imagerectangle($image, $x, $y, $x + $width, $y + $height, $border);
    }

    private function drawImgSectionTitle($image, string $title, int $x, int $width, int $y): int
    {
        $teal     = [15, 118, 110];
        $baseline = $y + 20;
        $this->imgText($image, $title, $x, $baseline, 17, $teal, true);

        $lineY  = $baseline + 8;
        $border = imagecolorallocate($image, 204, 204, 204);
        imageline($image, $x, $lineY, $x + $width, $lineY, $border);

        return $lineY + 10;
    }

    /**
     * Draws a bordered table: a teal header row (white uppercase labels)
     * then zebra-striped data rows — the raster equivalent of the PDF's
     * table.data-table. $columns items: ['label' => ..., 'width' => px,
     * 'align' => 'left'|'right'|'center']. $rows items are plain arrays
     * of cell values in column order; a cell may instead be
     * ['badge' => true, 'label' => ..., 'status' => ...] to render a
     * colored status pill. Returns the y position just below the table.
     */
    private function drawImgTable($image, int $x, int $y, int $width, array $columns, array $rows, int $rowHeight = 26): int
    {
        $tableTop  = $y;
        $teal      = imagecolorallocate($image, 15, 118, 110);
        $border    = imagecolorallocate($image, 229, 229, 229);
        $zebra     = imagecolorallocate($image, 250, 250, 250);
        $textColor = [26, 26, 26];

        imagefilledrectangle($image, $x, $y, $x + $width, $y + $rowHeight, $teal);
        $colX = $x;
        foreach ($columns as $col) {
            $labelX = $this->imgColumnX($colX, $col);
            $this->imgText($image, strtoupper($col['label']), $labelX, $y + intdiv($rowHeight, 2) + 5, 11.5, [255, 255, 255], true, $col['align'] ?? 'left');
            $colX += $col['width'];
        }
        $y += $rowHeight;

        foreach ($rows as $i => $row) {
            if ($i % 2 === 1) {
                imagefilledrectangle($image, $x, $y, $x + $width, $y + $rowHeight, $zebra);
            }
            $colX = $x;
            foreach ($columns as $ci => $col) {
                $cell   = $row[$ci] ?? '';
                $labelX = $this->imgColumnX($colX, $col);
                if (is_array($cell) && ($cell['badge'] ?? false)) {
                    $this->drawImgStatusBadge($image, $colX + 10, $y + 4, $cell['label'], $cell['status'], $rowHeight - 8);
                } else {
                    $text = $this->imgTruncate((string) $cell, $col['width'] - 20, 12);
                    $this->imgText($image, $text, $labelX, $y + intdiv($rowHeight, 2) + 4, 12, $textColor, false, $col['align'] ?? 'left');
                }
                $colX += $col['width'];
            }
            $y += $rowHeight;
            imageline($image, $x, $y, $x + $width, $y, $border);
        }

        imagerectangle($image, $x, $tableTop, $x + $width, $y, $border);

        return $y;
    }

    private function drawImgStationTable($image, string $title, $data, array $counts, int $x, int $y, int $width): void
    {
        $heading = $title . ' (' . $counts['online'] . ' online / ' . $data->count() . ' total)';
        $y = $this->drawImgSectionTitle($image, $heading, $x, $width, $y);

        [$noW, $stationW, $totalW, $latestW, $statusW] = $this->imgColumnWidths($width, [0.06, 0.36, 0.16, 0.24, 0.18]);
        $columns = [
            ['label' => 'No.',     'width' => $noW, 'align' => 'center'],
            ['label' => 'Station', 'width' => $stationW],
            ['label' => 'Total',   'width' => $totalW, 'align' => 'right'],
            ['label' => 'Latest',  'width' => $latestW],
            ['label' => 'Status',  'width' => $statusW],
        ];

        $shown = $data->take(self::IMAGE_MAX_TABLE_ROWS);
        $rows  = [];
        foreach ($shown as $i => $item) {
            $rows[] = [
                $i + 1,
                (string) $item->station,
                number_format($item->total),
                $item->latest_at ? \Carbon\Carbon::parse($item->latest_at)->format('Y-m-d H:i') : '—',
                ['badge' => true, 'label' => ucfirst($item->status), 'status' => $item->status],
            ];
        }

        if ($rows === []) {
            $rows[] = ['—', 'No stations available', '', '', ['badge' => true, 'label' => 'N/A', 'status' => 'unknown']];
        }

        $y = $this->drawImgTable($image, $x, $y, $width, $columns, $rows, 24);

        if ($data->count() > self::IMAGE_MAX_TABLE_ROWS) {
            $note = '+' . ($data->count() - self::IMAGE_MAX_TABLE_ROWS) . ' more not shown in this snapshot — see the PDF report for the full list.';
            $this->imgText($image, $note, $x, $y + 16, 11, [136, 136, 136]);
        }
    }

    private function drawImgFooter($image, int $x, int $width, int $baselineBottom, $generatedAt): void
    {
        $gray  = [136, 136, 136];
        $line1 = 'This report reflects station status at the time it was generated and may not match a subsequently refreshed dashboard.';
        $line2 = '© ' . $generatedAt->format('Y') . ' Uplink Integrated Solutions Inc. All rights reserved.';

        $this->imgText($image, $line1, $x + intdiv($width, 2), $baselineBottom - 16, 10.5, $gray, false, 'center');
        $this->imgText($image, $line2, $x + intdiv($width, 2), $baselineBottom, 10.5, $gray, false, 'center');
    }

    private function drawImgStatusBadge($image, int $x, int $y, string $label, string $status, int $height): void
    {
        [$r, $g, $b] = $this->statusRgb($status);
        $bg          = imagecolorallocate($image, $r, $g, $b);
        $labelUpper  = strtoupper($label);
        $badgeWidth  = (int) ($this->imgTextWidth($labelUpper, 10, true) + 16);

        imagefilledrectangle($image, $x, $y, $x + $badgeWidth, $y + $height, $bg);
        $this->imgText($image, $labelUpper, $x + 8, $y + $height - 6, 10, [255, 255, 255], true);
    }

    private function statusRgb(string $status): array
    {
        return match ($status) {
            'online', 'good'       => [22, 163, 74],
            'idle', 'warning'      => [217, 119, 6],
            'offline', 'critical'  => [220, 38, 38],
            default                => [107, 114, 128],
        };
    }

    /**
     * >=100 good / >=81 warning / else critical bands as $systemStatusClass
     * in reports/system-status.blade.php — used ONLY for uptime %, where
     * high = good. Do not use this for storage; see percentStatusKey's
     * doc comment and storageStatusKey below.
     */
    private function percentStatusKey(?float $percent): string
    {
        if ($percent === null) return 'warning';
        if ($percent >= 100)   return 'good';
        if ($percent >= 81)    return 'warning';
        return 'critical';
    }

    private function percentStatusLabel(?float $percent): string
    {
        if ($percent === null) return 'N/A';
        return match ($this->percentStatusKey($percent)) {
            'good'    => 'Good',
            'warning' => 'Warning',
            default   => 'Critical',
        };
    }

    /**
     * Storage free-space % bands, mirroring $storageStatusClass in
     * reports/system-status.blade.php: 0-10% free = critical, 11-20%
     * free = warning, 21-100% free = good. $percent here is % FREE
     * (see buildReportContext()'s storagePercent, which already inverts
     * buildSystemHealth()'s % used before handing it off).
     */
    private function storageStatusKey(?float $percent): string
    {
        if ($percent === null) return 'warning';
        if ($percent <= 10)    return 'critical';
        if ($percent <= 20)    return 'warning';
        return 'good';
    }

    private function storageStatusLabel(?float $percent): string
    {
        if ($percent === null) return 'N/A';
        return match ($this->storageStatusKey($percent)) {
            'good'    => 'Good',
            'warning' => 'Warning',
            default   => 'Critical',
        };
    }

    /**
     * CPU/memory usage % bands (high = bad) — same 85/60 split as the
     * live dashboard's $barColor closure in buildSystemHealth(), just
     * expressed as good/warning/critical instead of Tailwind classes so
     * Telegram alerts read the same "is this bad?" signal the dashboard
     * shows in red/amber/green.
     */
    private function usageStatusKey(float $percent): string
    {
        if ($percent >= 85) return 'critical';
        if ($percent >= 60) return 'warning';
        return 'good';
    }

    private function usageStatusLabel(float $percent): string
    {
        return match ($this->usageStatusKey($percent)) {
            'good'    => 'Good',
            'warning' => 'Warning',
            default   => 'Critical',
        };
    }

    /**
     * Same true/false/null -> Online/Offline/Unknown bands as
     * $serviceStatusClass in reports/system-status.blade.php (used for
     * MQTT/database/EMS and the uptime row).
     */
    private function boolStatusKey(?bool $isUp): string
    {
        if ($isUp === null) return 'idle';
        return $isUp ? 'online' : 'offline';
    }

    private function boolStatusLabel(?bool $isUp): string
    {
        if ($isUp === null) return 'Unknown';
        return $isUp ? 'Online' : 'Offline';
    }

    /**
     * Splits $totalWidth across columns by proportion (each 0-1, summing
     * to 1.0). The last column absorbs whatever rounding left over so
     * columns always sum to exactly $totalWidth — no 1-2px gap or overlap
     * at the right edge of a table.
     */
    private function imgColumnWidths(int $totalWidth, array $proportions): array
    {
        $widths    = [];
        $used      = 0;
        $lastIndex = count($proportions) - 1;

        foreach ($proportions as $i => $proportion) {
            if ($i === $lastIndex) {
                $widths[] = $totalWidth - $used;
            } else {
                $w = (int) round($totalWidth * $proportion);
                $widths[] = $w;
                $used += $w;
            }
        }

        return $widths;
    }

    private function imgColumnX(int $colX, array $col): int
    {
        return match ($col['align'] ?? 'left') {
            'right'  => $colX + $col['width'] - 10,
            'center' => $colX + intdiv($col['width'], 2),
            default  => $colX + 10,
        };
    }

    /**
     * Locates the DejaVu Sans TTF this app already ships as a dompdf
     * dependency (see reports/system-status.blade.php's "DejaVu Sans"
     * font-family) so the JPEG export can use real, antialiased text
     * without needing a new font file or package.
     */
    private function fontPath(bool $bold = false): ?string
    {
        $direct = $bold
            ? base_path('vendor/dompdf/dompdf/lib/fonts/DejaVuSans-Bold.ttf')
            : base_path('vendor/dompdf/dompdf/lib/fonts/DejaVuSans.ttf');

        if (is_readable($direct)) {
            return $direct;
        }

        // Filename has varied slightly across dompdf versions
        // (DejaVuSans-Bold.ttf vs DejaVuSansBold.ttf) — fall back to a
        // glob match rather than hardcoding one exact name.
        $fontsDir = base_path('vendor/dompdf/dompdf/lib/fonts');
        if (is_dir($fontsDir)) {
            $pattern = $bold ? '/DejaVuSans*Bold*.ttf' : '/DejaVuSans.ttf';
            $matches = glob($fontsDir . $pattern);
            if (!empty($matches)) {
                return $matches[0];
            }
        }

        return null;
    }

    /**
     * Draws $text with its origin at ($x, $y). $y is the text baseline
     * (TTF convention), not the top of the glyphs. Falls back to GD's
     * built-in bitmap font if the DejaVu TTF can't be found, so a missing
     * font file degrades the image rather than failing the whole export.
     */
    private function imgText($image, string $text, int $x, int $y, float $size, array $rgb, bool $bold = false, string $align = 'left'): void
    {
        $color = imagecolorallocate($image, $rgb[0], $rgb[1], $rgb[2]);
        $font  = $this->fontPath($bold);

        if ($font) {
            $width = $this->imgTextWidth($text, $size, $bold);
            $drawX = match ($align) {
                'right'  => $x - $width,
                'center' => $x - ($width / 2),
                default  => $x,
            };
            imagettftext($image, $size, 0, (int) round($drawX), $y, $color, $font, $text);
            return;
        }

        $gdFont = 5; // largest of GD's built-in bitmap fonts
        $width  = imagefontwidth($gdFont) * strlen($text);
        $drawX  = match ($align) {
            'right'  => $x - $width,
            'center' => $x - ($width / 2),
            default  => $x,
        };
        imagestring($image, $gdFont, (int) round($drawX), $y - imagefontheight($gdFont), $text, $color);
    }

    private function imgTextWidth(string $text, float $size, bool $bold = false): float
    {
        $font = $this->fontPath($bold);
        if ($font) {
            $bbox = imagettfbbox($size, 0, $font, $text);
            return $bbox[2] - $bbox[0];
        }

        return (float) (imagefontwidth(5) * strlen($text));
    }

    /**
     * Shrinks $text with a trailing ellipsis until it fits $maxWidth,
     * binary-searching the cut point rather than trimming char-by-char —
     * this runs per-cell on every table row, so it needs to stay cheap.
     */
    private function imgTruncate(string $text, float $maxWidth, float $size, bool $bold = false): string
    {

        mb_internal_encoding('UTF-8');
        mb_http_output('UTF-8');

        if ($maxWidth <= 0 || $this->imgTextWidth($text, $size, $bold) <= $maxWidth) {
            return $text;
        }

        $ellipsis = '…';
        $lo    = 0;
        $hi    = mb_strlen($text);
        $best  = $ellipsis;

        while ($lo <= $hi) {
            $mid       = intdiv($lo + $hi, 2);
            $candidate = mb_substr($text, 0, $mid) . $ellipsis;
            if ($this->imgTextWidth($candidate, $size, $bold) <= $maxWidth) {
                $best = $candidate;
                $lo   = $mid + 1;
            } else {
                $hi = $mid - 1;
            }
        }

        return $best;
    }
}