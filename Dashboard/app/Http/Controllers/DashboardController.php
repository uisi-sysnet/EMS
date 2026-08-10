<?php

namespace App\Http\Controllers;

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

        // ---------- Seismic (from 'seismic' connection) ----------
        $seismicData = DB::connection('seismic')
            ->table('station_metrics')
            ->select(
                'station_name as station',
                'station_id',                     // not directly used but included if needed
                DB::raw('MIN(time) as installed_at'),
                DB::raw('MAX(time) as latest_at'),
                DB::raw('COUNT(*) as total')
            )
            ->groupBy('station_id', 'station_name')
            ->orderBy('station_name')
            ->get()
            ->map(function ($item) {
                // The view expects an 'ip' field – we'll use station_id as a placeholder
                $item->ip = $item->station_id;   // or '--' or null
                return $item;
            })
            ->sortByDesc('total')
            ->values();

        return view('index', compact('airQualityData', 'seismicData'));
    }
}