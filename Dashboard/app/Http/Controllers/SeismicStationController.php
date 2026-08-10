<?php

namespace App\Http\Controllers;

use App\Models\SeismicStation;
use Illuminate\Http\Request;

class SeismicStationController extends Controller
{
    /**
     * Display a listing of seismic stations.
     */
    public function index()
    {
        $stations = SeismicStation::orderBy('station_id')->get();
        return view('seismic_stations', compact('stations'));
    }

    /**
     * Show the form for editing a seismic station.
     */
    public function edit(string $station_id)
    {
        $station = SeismicStation::findOrFail($station_id);
        return response()->json($station);
    }

    /**
     * Store a newly created seismic station.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'station_name' => 'nullable|string|max:100',
            'enabled'      => 'sometimes|boolean',
            'latitude'     => 'nullable|numeric|between:-90,90',
            'longitude'    => 'nullable|numeric|between:-180,180',
            'elevation_m'  => 'nullable|numeric',
        ]);

        // Add station_id from request (no validation, same pattern as station_mn)
        $validated['station_id'] = $request->input('station_id');
        $validated['enabled'] = $request->has('enabled') ? filter_var($request->enabled, FILTER_VALIDATE_BOOLEAN) : true;

        SeismicStation::create($validated);

        return redirect()->route('seismic-stations.index')
                         ->with('success', 'Seismic station created successfully.');
    }

    /**
     * Update the specified seismic station.
     */
    public function update(Request $request, string $station_id)
    {
        $station = SeismicStation::findOrFail($station_id);

        $validated = $request->validate([
            'station_name' => 'nullable|string|max:100',
            'enabled'      => 'sometimes|boolean',
            'latitude'     => 'nullable|numeric|between:-90,90',
            'longitude'    => 'nullable|numeric|between:-180,180',
            'elevation_m'  => 'nullable|numeric',
        ]);

        $validated['station_id'] = $request->input('station_id');
        $validated['enabled'] = $request->has('enabled') ? filter_var($request->enabled, FILTER_VALIDATE_BOOLEAN) : false;

        $station->update($validated);

        return redirect()->route('seismic-stations.index')
                         ->with('success', 'Seismic station updated successfully.');
    }

    /**
     * Remove the specified seismic station.
     */
    public function destroy(string $station_id)
    {
        $station = SeismicStation::findOrFail($station_id);
        $station->delete();

        return redirect()->route('seismic-stations.index')
                         ->with('success', 'Seismic station deleted successfully.');
    }
}