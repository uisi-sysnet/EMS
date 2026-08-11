<?php

namespace App\Http\Controllers;

use App\Models\Station;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class StationController extends Controller
{
    /**
     * Display a listing of stations (excluding deleted).
     */
    public function index()
    {
        $stations = Station::withCount('sensorData')
            ->where('deleted', false) // Only show non-deleted stations
            ->orderBy('station_mn')
            ->get();
            
        return view('stations', compact('stations'));
    }

    /**
     * Show the form for editing a station.
     */
    public function edit(string $station_mn)
    {
        $station = Station::where('station_mn', $station_mn)
            ->where('deleted', false)
            ->firstOrFail();
        return response()->json($station);
    }

    /**
     * Store a newly created station.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'station_name' => 'nullable|string|max:100',
            'enabled'      => 'sometimes|boolean',
            'latitude'     => 'nullable|numeric|between:-90,90',
            'longitude'    => 'nullable|numeric|between:-180,180',
            'lead_ip'      => 'nullable|string|max:64',
            'lead_port'    => 'nullable|integer|min:0|max:65535',
            'lead_slave'   => 'nullable|integer',
        ]);

        $validated['station_mn'] = $request->input('station_mn');
        $validated['enabled'] = $request->has('enabled') ? filter_var($request->enabled, FILTER_VALIDATE_BOOLEAN) : true;
        $validated['deleted'] = false; // New stations are not deleted

        Station::create($validated);

        return redirect()->route('stations.index')
                         ->with('success', 'Station created successfully.');
    }

    /**
     * Update the specified station.
     */
    public function update(Request $request, string $station_mn)
    {
        $station = Station::where('station_mn', $station_mn)
            ->where('deleted', false)
            ->firstOrFail();
        
        $validated = $request->validate([
            'station_name' => 'nullable|string|max:100',
            'enabled'      => 'sometimes|boolean',
            'latitude'     => 'nullable|numeric|between:-90,90',
            'longitude'    => 'nullable|numeric|between:-180,180',
            'lead_ip'      => 'nullable|string|max:64',
            'lead_port'    => 'nullable|integer|min:0|max:65535',
            'lead_slave'   => 'nullable|integer',
        ]);

        $validated['enabled'] = $request->has('enabled') ? filter_var($request->enabled, FILTER_VALIDATE_BOOLEAN) : false;

        $station->update($validated);

        return redirect()->route('stations.index')
                         ->with('success', 'Station updated successfully.');
    }

    /**
     * Remove or soft-delete the specified station.
     */
    public function destroy(string $station_mn)
    {
        $station = Station::where('station_mn', $station_mn)
            ->where('deleted', false)
            ->firstOrFail();

        // Check if station has sensor data
        $dataCount = $station->sensorData()->count();

        if ($dataCount > 0) {
            // Station has data - mark as deleted but keep the data
            $station->update(['deleted' => true]);
            $message = 'Station has been deactivated (hidden) but its data has been preserved.';
        } else {
            // No data - permanently delete
            $station->delete();
            $message = 'Station deleted successfully.';
        }

        return redirect()->route('stations.index')
                         ->with('success', $message);
    }
}