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
     * Show the form for editing a station.
     */
    public function edit(string $station_mn)
    {
        $station = Station::findOrFail($station_mn);
        return response()->json($station);
    }

    /**
     * Store a newly created station.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'station_mn'   => 'required|string|max:32|unique:' . (new Station())->getTable() . ',station_mn',
            'station_name' => 'nullable|string|max:100',
            'enabled'      => 'sometimes|boolean',
            'latitude'     => 'nullable|numeric|between:-90,90',
            'longitude'    => 'nullable|numeric|between:-180,180',
            'lead_ip'      => 'nullable|string|max:64',
            'lead_port'    => 'nullable|integer|min:0|max:65535',
            'lead_slave'   => 'nullable|integer',
        ]);

        $validated['enabled'] = $request->has('enabled') ? filter_var($request->enabled, FILTER_VALIDATE_BOOLEAN) : true;

        Station::create($validated);

        return redirect()->route('stations.index')
                        ->with('success', 'Station created successfully.');
    }

    public function update(Request $request, string $station_mn)
    {
        $station = Station::findOrFail($station_mn);
        
        $validated = $request->validate([
            'station_mn'   => [
                'required',
                'string',
                'max:32',
                Rule::unique((new Station())->getTable(), 'station_mn')->ignore($station_mn, 'station_mn')
            ],
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
     * Remove the specified station.
     */
    public function destroy(string $station_mn)
    {
        $station = Station::findOrFail($station_mn);
        $station->delete();

        return redirect()->route('stations.index')
                         ->with('success', 'Station deleted successfully.');
    }
}