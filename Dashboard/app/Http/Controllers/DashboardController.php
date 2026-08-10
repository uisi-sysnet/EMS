<?php

namespace App\Http\Controllers;

use App\Models\SeismicStation;
use App\Models\Station;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        [$airQualityData, $seismicData] = $this->buildDashboardData();

        return view('index', compact('airQualityData', 'seismicData'));
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
        $generatedBy = optional($request->user())->name
            ?? optional($request->user())->username
            ?? 'Unknown user';

        $pdf = Pdf::loadView('reports.system-status', [
            'airQualityData'   => $airQualityData,
            'seismicData'      => $seismicData,
            'airQualityCounts' => $airQualityCounts,
            'seismicCounts'    => $seismicCounts,
            'generatedAt'      => $generatedAt,
            'generatedBy'      => $generatedBy,
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
                'percent' => $diskPercent,
                'used'    => $this->formatBytes($diskUsed),
                'total'   => $this->formatBytes($diskTotal),
                'colors'  => $barColor($diskPercent),
            ],
            'uptime' => [
                'days'    => intdiv($uptimeSeconds, 86400),
                'hours'   => intdiv($uptimeSeconds % 86400, 3600),
                'minutes' => intdiv($uptimeSeconds % 3600, 60),
            ],
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