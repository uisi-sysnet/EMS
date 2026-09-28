<?php

namespace App\Http\Controllers;

use App\Models\WaterLevel;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class WaterLevelStationController extends Controller
{
    /**
     * Display a listing of water level stations.
     */
    public function index()
    {
        $stations = WaterLevel::query()
            // If you add the sensor_data table, this gives you sensor_data_count
            // on each row, which the Blade already checks via $station->sensor_data_count.
            ->withCount('sensorData')
            ->orderBy('station_mn')
            ->get();

        $deletedStations = WaterLevel::onlyTrashed()
            ->withCount('sensorData')
            ->orderBy('station_mn')
            ->get();

        return view('inventory.water_level', compact('stations', 'deletedStations'));
    }

    /**
     * Store a newly created station.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'station_mn'          => ['required', 'string', 'max:14',
                                      Rule::unique('water_level.stations', 'station_mn')],
            'station_name'        => ['required', 'string', 'max:32'],
            'enabled'             => ['nullable', 'boolean'],
            'location'            => ['nullable', 'string', 'max:255'],
            'latitude'            => ['nullable', 'numeric', 'between:4.5,21.5'],
            'longitude'           => ['nullable', 'numeric', 'between:116,127'],
            'installation_height' => ['nullable', 'numeric'],
            'elevation_height'    => ['nullable', 'numeric'],
            'lead_ip'             => ['required', 'ip', 'max:15',
                                      Rule::unique('water_level.stations', 'lead_ip')],
            'lead_port'           => ['nullable', 'integer', 'min:1', 'max:65535'],
            'lead_slave'          => ['nullable', 'integer', 'min:1', 'max:255'],
        ], [
            'station_mn.unique' => 'This Station MN already exists (including deleted stations).',
            'lead_ip.unique'    => 'This IP Address is already assigned to another station (including deleted stations).',
        ]);

        $validated['enabled'] = $request->boolean('enabled', true);

        WaterLevel::create($validated);

        return redirect()
            ->route('inventory.water-level-stations.index')
            ->with('success', "Station \"{$validated['station_mn']}\" created successfully.");
    }

    /**
     * Return a station as JSON for the edit modal.
     */
    public function edit($station_mn)
    {
        $station = WaterLevel::where('station_mn', $station_mn)->firstOrFail();

        return response()->json([
            'station_mn'          => $station->station_mn,
            'station_name'        => $station->station_name,
            'enabled'             => $station->enabled,
            'location'            => $station->location,
            'latitude'            => $station->latitude,
            'longitude'           => $station->longitude,
            'installation_height' => $station->installation_height,
            'elevation_height'    => $station->elevation_height,
            'lead_ip'             => $station->lead_ip,
            'lead_port'           => $station->lead_port,
            'lead_slave'          => $station->lead_slave,
        ]);
    }

    /**
     * Update an existing station.
     */
    public function update(Request $request, $station_mn)
    {
        $station = WaterLevel::where('station_mn', $station_mn)->firstOrFail();

        $validated = $request->validate([
            'station_mn'          => ['required', 'string', 'max:14',
                                      Rule::unique('water_level.stations', 'station_mn')
                                          ->ignore($station->id)],
            'station_name'        => ['required', 'string', 'max:32'],
            'enabled'             => ['nullable', 'boolean'],
            'location'            => ['nullable', 'string', 'max:255'],
            'latitude'            => ['nullable', 'numeric', 'between:4.5,21.5'],
            'longitude'           => ['nullable', 'numeric', 'between:116,127'],
            'installation_height' => ['nullable', 'numeric'],
            'elevation_height'    => ['nullable', 'numeric'],
            'lead_ip'             => ['required', 'ip', 'max:15',
                                      Rule::unique('water_level.stations', 'lead_ip')
                                          ->ignore($station->id)],
            'lead_port'           => ['nullable', 'integer', 'min:1', 'max:65535'],
            'lead_slave'          => ['nullable', 'integer', 'min:1', 'max:255'],
        ], [
            'station_mn.unique' => 'This Station MN already exists (including deleted stations).',
            'lead_ip.unique'    => 'This IP Address is already assigned to another station (including deleted stations).',
        ]);

        $validated['enabled'] = $request->boolean('enabled', true);

        $station->update($validated);

        return redirect()
            ->route('inventory.water-level-stations.index')
            ->with('success', "Station \"{$station->station_mn}\" updated successfully.");
    }

    /**
     * Soft-delete a station.
     */
    public function destroy($station_mn)
    {
        $station = WaterLevel::where('station_mn', $station_mn)->firstOrFail();
        $station->delete();

        return redirect()
            ->route('inventory.water-level-stations.index')
            ->with('success', "Station \"{$station->station_mn}\" deleted successfully.");
    }

    /**
     * Restore a soft-deleted station.
     */
    public function restore($station_mn)
    {
        $station = WaterLevel::onlyTrashed()
            ->where('station_mn', $station_mn)
            ->firstOrFail();

        $station->restore();

        return redirect()
            ->route('inventory.water-level-stations.index')
            ->with('success', "Station \"{$station->station_mn}\" restored successfully.");
    }

    /**
     * Check whether a station has sensor data (used by the delete confirm dialog).
     */
    public function checkData($station_mn)
    {
        $station = WaterLevel::withTrashed()
            ->where('station_mn', $station_mn)
            ->first();

        if (!$station) {
            return response()->json([
                'hasData'   => false,
                'dataCount' => 0,
                'exists'    => false,
            ]);
        }

        $count = 0;
        try {
            $count = $station->sensorData()->count();
        } catch (\Throwable $e) {
            // sensor_data table not created yet — silently report zero.
            $count = 0;
        }

        return response()->json([
            'hasData'   => $count > 0,
            'dataCount' => $count,
            'exists'    => true,
        ]);
    }
}