<?php

namespace App\Http\Controllers;

use App\Models\SeismicStation;
use App\Models\Station;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        // ---------- Air Quality ----------
        // Source of truth for WHICH stations exist is the app's own
        // `stations` table (managed via Manage Stations). We then left-join
        // in whatever readings happen to exist in the 'aq' connection's
        // sensor_data table (populated by the external Python ingestion
        // service). This way a station shows up on the dashboard as soon
        // as it's registered, even before its first sensor reading comes
        // in — instead of being invisible until sensor_data has rows for it.
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
                    'installed_at' => $reading->installed_at ?? null,
                    'latest_at'    => $reading->latest_at ?? null,
                    'total'        => $reading->total ?? 0,
                ];
            })
            ->sortByDesc('total')
            ->values();

        // ---------- Seismic ----------
        // Unlike air quality (where stations must be manually provisioned
        // with lead_ip/port before they can talk to the app), seismic
        // devices just start pushing readings straight into station_metrics
        // once they're online — there's nothing to pre-configure. So instead
        // of requiring a manual "Add Station" step first, we auto-register
        // any station_id that's already reporting data but doesn't have a
        // registry row yet. Existing registrations (including anything an
        // admin has manually renamed/disabled) are left untouched — this
        // only adds new ones, never overwrites or removes.
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
                    // The view expects an 'ip' field – seismic stations don't
                    // have one, so we reuse station_id as a placeholder, same
                    // as the original query did.
                    'ip'           => $station->station_id,
                    'installed_at' => $reading->installed_at ?? null,
                    'latest_at'    => $reading->latest_at ?? null,
                    'total'        => $reading->total ?? 0,
                ];
            })
            ->sortByDesc('total')
            ->values();

        return view('index', compact('airQualityData', 'seismicData'));
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