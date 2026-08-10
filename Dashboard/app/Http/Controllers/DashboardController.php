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
     * Shared query/merge logic for both the live dashboard and the PDF
     * report, so the two never drift out of sync.
     *
     * @return array{0: \Illuminate\Support\Collection, 1: \Illuminate\Support\Collection}
     */
    private function buildDashboardData(): array
    {
        // ---------- Air Quality ----------
        $stations = Station::orderBy('station_mn')->get();

        $aqReadings = DB::connection('aq')
            ->table('sensor_data')
            ->select(
                'station_mn',
                'ip_address as ip',
                DB::raw('MIN(data_time) as installed_at'),
                DB::raw('MAX(data_time) as latest_at'),
                DB::raw('COUNT(*) as total')
            )
            ->groupBy('station_mn', 'ip_address')
            ->get()
            ->keyBy('station_mn');

        $airQualityData = $stations
            ->map(function ($station) use ($aqReadings) {
                $reading = $aqReadings->get($station->station_mn);

                return (object) [
                    'station_mn'   => $station->station_mn,
                    'station'      => $station->station_name ?: $station->station_mn,
                    'ip'           => $reading->ip ?? $station->lead_ip,
                    // TODO: swap for a real location/address column on the
                    // stations table if one exists — currently falling
                    // back to the IP, same as the rest of the dashboard.
                    'location'     => $station->location ?? $station->lead_ip ?? '—',
                    'installed_at' => $reading->installed_at ?? null,
                    'latest_at'    => $reading->latest_at ?? null,
                    'total'        => $reading->total ?? 0,
                ];
            })
            ->sortByDesc('total')
            ->values();

        // ---------- Seismic ----------
        $this->syncNewSeismicStations();

        $seismicStations = SeismicStation::orderBy('station_id')->get();

        $seismicReadings = DB::connection('seismic')
            ->table('station_metrics')
            ->select(
                'station_id',
                'station_name',
                DB::raw('MIN(time) as installed_at'),
                DB::raw('MAX(time) as latest_at'),
                DB::raw('COUNT(*) as total')
            )
            ->groupBy('station_id', 'station_name')
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
                    'installed_at' => $reading->installed_at ?? null,
                    'latest_at'    => $reading->latest_at ?? null,
                    'total'        => $reading->total ?? 0,
                ];
            })
            ->sortByDesc('total')
            ->values();

        return [$airQualityData, $seismicData];
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
                $minutesAgo = \Carbon\Carbon::parse($item->latest_at)->diffInMinutes(now());
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