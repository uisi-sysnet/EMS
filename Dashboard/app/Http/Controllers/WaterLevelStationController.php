<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class WaterLevelStationController extends Controller
{
    /**
     * Display a listing of water level stations (dummy data only).
     */
    public function index()
    {
        $stations = collect([
            (object) [
                'id' => 1,
                'station_mn' => 'WLS-001',
                'station_name' => 'Alabang River Level',
                'enabled' => true,
                'sensor_data_count' => 1240,
                'location' => 'Brgy. Alabang, Muntinlupa City',
                'latitude' => '14.4234',
                'longitude' => '121.0342',
                'installation_height' => '3.50',
                'elevation_height' => '12.75',
                'lead_ip' => '192.168.1.101',
                'lead_port' => 8899,
                'lead_slave' => 1,
                'updated_at' => now()->subMinutes(5),
            ],
            (object) [
                'id' => 2,
                'station_mn' => 'WLS-002',
                'station_name' => 'Bayanan Creek Monitor',
                'enabled' => true,
                'sensor_data_count' => 0,
                'location' => 'Brgy. Bayanan, Muntinlupa City',
                'latitude' => '14.4089',
                'longitude' => '121.0456',
                'installation_height' => '2.25',
                'elevation_height' => '8.40',
                'lead_ip' => '192.168.1.102',
                'lead_port' => 8899,
                'lead_slave' => 2,
                'updated_at' => now()->subHours(2),
            ],
        ]);

        $deletedStations = collect([
            (object) [
                'id' => 3,
                'station_mn' => 'WLS-003',
                'station_name' => 'Old Putatan Station',
                'enabled' => false,
                'sensor_data_count' => 0,
                'location' => 'Brgy. Putatan, Muntinlupa City',
                'latitude' => '14.4010',
                'longitude' => '121.0410',
                'installation_height' => '1.80',
                'elevation_height' => '6.20',
                'lead_ip' => '192.168.1.103',
                'lead_port' => 8899,
                'lead_slave' => 1,
                'updated_at' => now()->subDays(3),
            ],
        ]);

        return view('inventory.water_level', compact('stations', 'deletedStations'));
    }

    public function store(Request $request)
    {
        return redirect()
            ->route('inventory.water-level-stations.index')
            ->with('success', 'Water level station created successfully (dummy).');
    }

    public function edit($station_mn)
    {
        return response()->json([
            'station_mn'          => $station_mn,
            'station_name'        => 'Dummy Station',
            'location'            => 'Brgy. Alabang, Muntinlupa City',
            'latitude'            => '14.4234',
            'longitude'           => '121.0342',
            'installation_height' => '3.50',
            'elevation_height'    => '12.75',
            'lead_ip'             => '192.168.1.101',
            'lead_port'           => 8899,
            'lead_slave'          => 1,
            'enabled'             => true,
        ]);
    }
    
    public function update(Request $request, $station_mn)
    {
        return redirect()
            ->route('inventory.water-level-stations.index')
            ->with('success', 'Water level station updated successfully (dummy).');
    }

    public function destroy($station_mn)
    {
        return redirect()
            ->route('inventory.water-level-stations.index')
            ->with('success', 'Water level station deleted successfully (dummy).');
    }

    public function restore($station_mn)
    {
        return redirect()
            ->route('inventory.water-level-stations.index')
            ->with('success', 'Water level station restored successfully (dummy).');
    }

    public function checkData($station_mn)
    {
        // Match the keys the JS expects: hasData, dataCount
        return response()->json([
            'hasData'   => $station_mn === 'WLS-001',
            'dataCount' => $station_mn === 'WLS-001' ? 1240 : 0,
        ]);
    }
}