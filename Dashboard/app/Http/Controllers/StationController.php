<?php

namespace App\Http\Controllers;

use App\Models\Station;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class StationController extends Controller
{
    /**
     * Display a listing of stations (only active/non-deleted).
     */
    public function index()
    {
        // This automatically excludes soft-deleted records
        $stations = Station::orderBy('station_mn')->get();
        return view('stations', compact('stations'));
    }

    /**
     * Display all stations including soft-deleted ones (optional admin view).
     */
    public function indexWithDeleted()
    {
        $stations = Station::withTrashed()->orderBy('station_mn')->get();
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
            'station_name' => 'nullable|string|max:100',
            'enabled'      => 'sometimes|boolean',
            'latitude'     => 'nullable|numeric|between:-90,90',
            'longitude'    => 'nullable|numeric|between:-180,180',
            'lead_ip'      => 'nullable|string|max:64',
            'lead_port'    => 'nullable|integer|min:0|max:65535',
            'lead_slave'   => 'nullable|integer',
        ]);

        // Add station_mn from request
        $validated['station_mn'] = $request->input('station_mn');
        $validated['enabled'] = $request->has('enabled') ? filter_var($request->enabled, FILTER_VALIDATE_BOOLEAN) : true;

        // Check if a soft-deleted station exists with this station_mn
        $deletedStation = Station::withTrashed()
                                 ->where('station_mn', $validated['station_mn'])
                                 ->first();

        if ($deletedStation) {
            // Restore the soft-deleted station instead of creating a new one
            $deletedStation->restore();
            $deletedStation->update($validated);
            return redirect()->route('stations.index')
                             ->with('success', 'Station restored and updated successfully.');
        }

        Station::create($validated);

        return redirect()->route('stations.index')
                         ->with('success', 'Station created successfully.');
    }

    /**
     * Update the specified station.
     */
    public function update(Request $request, string $station_mn)
    {
        $station = Station::findOrFail($station_mn);
        
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
     * Soft delete the specified station.
     */
    public function destroy(string $station_mn)
    {
        $station = Station::findOrFail($station_mn);
        $station->delete(); // This performs soft delete

        return redirect()->route('stations.index')
                         ->with('success', 'Station deleted successfully (can be restored).');
    }

    /**
     * Restore a soft-deleted station.
     */
    public function restore(string $station_mn)
    {
        $station = Station::withTrashed()->findOrFail($station_mn);
        $station->restore();

        return redirect()->route('stations.index')
                         ->with('success', 'Station restored successfully.');
    }

    /**
     * Permanently delete a station (force delete).
     */
    public function forceDelete(string $station_mn)
    {
        $station = Station::withTrashed()->findOrFail($station_mn);
        $station->forceDelete();

        return redirect()->route('stations.index')
                         ->with('success', 'Station permanently deleted.');
    }
}