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
            ->where('deleted', false)
            ->orderBy('station_mn')
            ->get();

        // Also load deleted stations for the modal
        $deletedStations = Station::withCount('sensorData')
            ->where('deleted', true)
            ->orderBy('station_mn')
            ->get();
            
        return view('stations', compact('stations', 'deletedStations'));
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
            'station_mn' => 'required|string|max:100|unique:stations,station_mn',  
            'station_name' => 'nullable|string|max:100|unique:stations,station_name', 
            'enabled'      => 'sometimes|boolean',
            'latitude'     => 'nullable|numeric|between:-90,90',
            'longitude'    => 'nullable|numeric|between:-180,180',
            'lead_ip'      => 'nullable|string|max:64|unique:stations,lead_ip',
            'lead_port'    => 'nullable|integer|min:0|max:65535',
            'lead_slave'   => 'nullable|integer',
        ]);

        $validated['station_mn'] = $request->input('station_mn');
        $validated['enabled'] = $request->has('enabled') ? filter_var($request->enabled, FILTER_VALIDATE_BOOLEAN) : true;
        $validated['deleted'] = false;

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
            'station_name' => 'nullable|string|max:100|unique:stations,station_name,' . $station_mn . ',station_mn',
            'enabled'      => 'sometimes|boolean',
            'latitude'     => 'nullable|numeric|between:-90,90',
            'longitude'    => 'nullable|numeric|between:-180,180',
            'lead_ip'      => 'nullable|string|max:64|unique:stations,lead_ip,' . $station_mn . ',station_mn',
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

        if ($station->hasData()) {
            $station->update(['deleted' => true]);
            $message = 'Station has been deactivated (hidden) but its data has been preserved.';
        } else {
            Station::where('station_mn', $station_mn)->delete();
            $message = 'Station deleted successfully.';
        }

        return redirect()->route('stations.index')
            ->with('success', $message);
    }

    /**
     * Restore a soft-deleted station.
     */
    public function restore(string $station_mn)
    {
        $station = Station::where('station_mn', $station_mn)
            ->where('deleted', true)
            ->firstOrFail();

        $station->update(['deleted' => false]);

        return redirect()->route('stations.index')
            ->with('success', 'Station restored successfully.');
    }
}