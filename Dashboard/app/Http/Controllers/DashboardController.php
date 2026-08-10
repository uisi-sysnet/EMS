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
        // Same pattern as air quality: `seismic_stations` (local app DB) is
        // the source of truth for which stations are registered. We left
        // -join readings from `station_metrics` on the 'seismic' connection
        // (IOT_seismic_sensor_data Postgres DB), keyed on station_id.
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
}