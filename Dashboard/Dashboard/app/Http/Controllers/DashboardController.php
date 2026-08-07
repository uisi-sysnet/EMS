<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        // ---------- Air Quality (from 'aq' connection) ----------
        $airQualityData = DB::connection('aq')
            ->table('sensor_data')
            ->select(
                'station_mn as station',
                'ip_address as ip',
                DB::raw('MIN(data_time) as installed_at'),
                DB::raw('MAX(data_time) as latest_at'),
                DB::raw('COUNT(*) as total')
            )
            ->groupBy('station_mn', 'ip_address')
            ->get()
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