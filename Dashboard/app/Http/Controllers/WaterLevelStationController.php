<?php

namespace App\Http\Controllers;

use App\Models\WaterLevel;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class WaterLevelStationController extends Controller
{
    public function index()
    {
        $stations = WaterLevel::orderBy('station_mn')->get();

        $deletedStations = WaterLevel::onlyTrashed()
            ->orderBy('station_mn')
            ->get();

        return view('inventory.water_level', compact('stations', 'deletedStations'));
    }

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

    public function destroy($station_mn)
    {
        $station = WaterLevel::where('station_mn', $station_mn)->firstOrFail();
        $station->delete();

        return redirect()
            ->route('inventory.water-level-stations.index')
            ->with('success', "Station \"{$station->station_mn}\" deleted successfully.");
    }

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
     * Stub — sensor_data table no longer exists.
     * Always reports zero so the delete confirmation behaves as "no data".
     */
    public function checkData($station_mn)
    {
        $exists = WaterLevel::withTrashed()
            ->where('station_mn', $station_mn)
            ->exists();

        return response()->json([
            'hasData'   => false,
            'dataCount' => 0,
            'exists'    => $exists,
        ]);
    }
}