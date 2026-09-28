<?php

namespace App\Http\Controllers;

use App\Models\WaterLevelStation;
use Illuminate\Http\Request;

class WaterLevelStationController extends Controller
{
    public function index()
    {
        $stations = WaterLevelStation::orderBy('station_name')->get();
        $deletedStations = WaterLevelStation::onlyTrashed()->get();
        
        return view('inventory.water_level', compact('stations', 'deletedStations'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'station_mn'   => 'required|string|max:14|unique:water_level_stations,station_mn',
            'station_name' => 'required|string|max:32|unique:water_level_stations,station_name',
            'enabled'      => 'nullable|boolean',
            'location'     => 'nullable|string|max:255',
            'latitude'     => 'nullable|numeric|between:4.5,21.5',
            'longitude'    => 'nullable|numeric|between:116.0,127.0',
            'lead_ip'      => 'required|ip|unique:water_level_stations,lead_ip',
            'lead_port'    => 'nullable|integer|between:1,65535',
            'lead_slave'   => 'nullable|integer|between:1,255',
        ]);

        WaterLevelStation::create($validated);

        return redirect()
            ->route('inventory.water-level-stations.index')
            ->with('success', 'Water level station created successfully.');
    }

    public function edit($station_mn)
    {
        $station = WaterLevelStation::where('station_mn', $station_mn)->firstOrFail();
        return response()->json($station);
    }

    public function update(Request $request, $station_mn)
    {
        $station = WaterLevelStation::where('station_mn', $station_mn)->firstOrFail();

        $validated = $request->validate([
            'station_mn'   => 'required|string|max:14|unique:water_level_stations,station_mn,' . $station->id,
            'station_name' => 'required|string|max:32|unique:water_level_stations,station_name,' . $station->id,
            'enabled'      => 'nullable|boolean',
            'location'     => 'nullable|string|max:255',
            'latitude'     => 'nullable|numeric|between:4.5,21.5',
            'longitude'    => 'nullable|numeric|between:116.0,127.0',
            'lead_ip'      => 'required|ip|unique:water_level_stations,lead_ip,' . $station->id,
            'lead_port'    => 'nullable|integer|between:1,65535',
            'lead_slave'   => 'nullable|integer|between:1,255',
        ]);

        $station->update($validated);

        return redirect()
            ->route('inventory.water-level-stations.index')
            ->with('success', 'Water level station updated successfully.');
    }

    public function destroy($station_mn)
    {
        $station = WaterLevelStation::where('station_mn', $station_mn)->firstOrFail();
        $station->delete();

        return redirect()
            ->route('inventory.water-level-stations.index')
            ->with('success', 'Water level station deleted successfully.');
    }

    public function restore($station_mn)
    {
        $station = WaterLevelStation::onlyTrashed()
            ->where('station_mn', $station_mn)
            ->firstOrFail();
        
        $station->restore();

        return redirect()
            ->route('inventory.water-level-stations.index')
            ->with('success', 'Water level station restored successfully.');
    }

    public function checkData($station_mn)
    {
        $station = WaterLevelStation::where('station_mn', $station_mn)->firstOrFail();
        
        // Dummy check — replace with real logic
        $hasData = true;       // e.g. $station->sensorReadings()->exists()
        $dataCount = 1240;     // e.g. $station->sensorReadings()->count()
        
        return response()->json([
            'hasData'   => $hasData,
            'dataCount' => $dataCount,
        ]);
    }
}