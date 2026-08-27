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
            
        return view('inventory.stations', compact('stations', 'deletedStations'));
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
     * Check if station has data (for delete confirmation)
     */
    public function checkData(string $station_mn)
    {
        $station = Station::where('station_mn', $station_mn)
            ->where('deleted', false)
            ->firstOrFail();
            
        $hasData = $station->hasData();
        $dataCount = $station->sensorData()->count();
        
        return response()->json([
            'hasData' => $hasData,
            'dataCount' => $dataCount
        ]);
    }

    /**
     * Store a newly created station.
     */
    public function store(Request $request)
    {
        // Custom validation with existence checks across all records
        $validated = $request->validate([
            'station_mn' => [
                'required',
                'string',
                'max:255',
                function ($attribute, $value, $fail) {
                    if (Station::existsWithTrashed($value)) {
                        $fail('The station MN "' . $value . '" is already taken. Please use a unique station MN.');
                    }
                }
            ],
            'station_name' => [
                'nullable',
                'string',
                'max:100',
                function ($attribute, $value, $fail) use ($request) {
                    if ($value && Station::existsByNameWithTrashed($value)) {
                        $fail('The station name "' . $value . '" is already taken. Please use a unique station name.');
                    }
                }
            ],
            'enabled'      => 'sometimes|boolean',
            'latitude'     => 'nullable|numeric|between:-90,90',
            'longitude'    => 'nullable|numeric|between:-180,180',
            'location' => 'nullable|string|max:255',
            'lead_ip'      => [
                'nullable',
                'string',
                'max:64',
                function ($attribute, $value, $fail) use ($request) {
                    if ($value && Station::existsByLeadIpWithTrashed($value)) {
                        $fail('The lead IP "' . $value . '" is already in use. Please use a unique lead IP.');
                    }
                }
            ],
            'lead_port'    => 'nullable|integer|min:0|max:65535',
            'lead_slave'   => 'nullable|integer',
        ]);

        $validated['enabled'] = $request->has('enabled') ? filter_var($request->enabled, FILTER_VALIDATE_BOOLEAN) : true;
        $validated['deleted'] = false;

        // Set auto-filled values
        $validated['lead_port'] = 8899;  // Default port
        $validated['lead_slave'] = 1;    // Default slave

        Station::create($validated);

        return redirect()->route('inventory.stations.index')
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
        
        // Custom validation with existence checks excluding current station
        $validated = $request->validate([
            'station_mn' => [
                'required',
                'string',
                'max:255',
                function ($attribute, $value, $fail) use ($station_mn) {
                    if ($value !== $station_mn) {
                        $exists = Station::where('station_mn', $value)
                            ->where('station_mn', '!=', $station_mn)
                            ->exists();
                        if ($exists) {
                            $fail('The station MN "' . $value . '" is already taken. Please use a unique station MN.');
                        }
                    }
                }
            ],
            'station_name' => [
                'nullable',
                'string',
                'max:100',
                function ($attribute, $value, $fail) use ($station_mn) {
                    if ($value) {
                        $exists = Station::where('station_name', $value)
                            ->where('station_mn', '!=', $station_mn)
                            ->exists();
                        if ($exists) {
                            $fail('The station name "' . $value . '" is already taken. Please use a unique station name.');
                        }
                    }
                }
            ],
            'enabled'      => 'sometimes|boolean',
            'latitude'     => 'nullable|numeric|between:-90,90',
            'longitude'    => 'nullable|numeric|between:-180,180',
            'location' => 'nullable|string|max:255',
            'lead_ip'      => [
                'nullable',
                'string',
                'max:64',
                function ($attribute, $value, $fail) use ($station_mn) {
                    if ($value) {
                        $exists = Station::where('lead_ip', $value)
                            ->where('station_mn', '!=', $station_mn)
                            ->exists();
                        if ($exists) {
                            $fail('The lead IP "' . $value . '" is already in use. Please use a unique lead IP.');
                        }
                    }
                }
            ],
            'lead_port'    => 'nullable|integer|min:0|max:65535',
            'lead_slave'   => 'nullable|integer',
        ]);

        $validated['enabled'] = $request->has('enabled') ? filter_var($request->enabled, FILTER_VALIDATE_BOOLEAN) : false;

        // Keep the auto-filled values
        $validated['lead_port'] = 8899;  // Default port
        $validated['lead_slave'] = 1;    // Default slave

        $station->update($validated);

        return redirect()->route('inventory.stations.index')
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

        return redirect()->route('inventory.stations.index')
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

        return redirect()->route('inventory.stations.index')
            ->with('success', 'Station restored successfully.');
    }
}