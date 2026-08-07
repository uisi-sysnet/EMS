<?php

namespace App\Http\Controllers;

use App\Models\Station;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class StationController extends Controller
{
    /**
     * Display a listing of stations.
     */
    public function index()
    {
        $stations = Station::orderBy('station_mn')->get();
        return view('stations', compact('stations'));
    }

    /**
     * Store a newly created station.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'station_mn'   => 'required|string|max:32|unique:aq.stations,station_mn',
            'station_name' => 'nullable|string|max:100',
            'enabled'      => 'sometimes|boolean',
            'latitude'     => 'nullable|numeric|between:-90,90',
            'longitude'    => 'nullable|numeric|between:-180,180',
            'lead_ip'      => 'nullable|string|max:64',
            'lead_port'    => 'nullable|integer|min:0|max:65535',
            'lead_slave'   => 'nullable|integer',
        ]);

        // Set default for enabled if not provided
        $validated['enabled'] = $request->has('enabled') ? filter_var($request->enabled, FILTER_VALIDATE_BOOLEAN) : true;

        Station::create($validated);

        return redirect()->route('stations.index')
                         ->with('success', 'Station created successfully.');
    }

    /**
     * Show the form for editing the specified station.
     */
    public function edit(Station $station)
    {
        return response()->json($station);
    }

    /**
     * Update the specified station.
     */
    public function update(Request $request, Station $station)
    {
        $validated = $request->validate([
            'station_mn'   => [
                'required',
                'string',
                'max:32',
                Rule::unique('aq.stations', 'station_mn')->ignore($station->id),
            ],
            'station_name' => 'nullable|string|max:100',
            'enabled'      => 'sometimes|boolean',
            'latitude'     => 'nullable|numeric|between:-90,90',
            'longitude'    => 'nullable|numeric|between:-180,180',
            'lead_ip'      => 'nullable|string|max:64',
            'lead_port'    => 'nullable|integer|min:0|max:65535',
            'lead_slave'   => 'nullable|integer',
        ]);

        // Handle enabled checkbox
        $validated['enabled'] = $request->has('enabled') ? filter_var($request->enabled, FILTER_VALIDATE_BOOLEAN) : false;

        $station->update($validated);

        return redirect()->route('stations.index')
                         ->with('success', 'Station updated successfully.');
    }

    /**
     * Remove the specified station.
     */
    public function destroy(Station $station)
    {
        $station->delete();

        return redirect()->route('stations.index')
                         ->with('success', 'Station deleted successfully.');
    }
}