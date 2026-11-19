<?php

namespace App\Http\Controllers;

use App\Models\SeismicStation;
use App\Services\SeismicFutureReadings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

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

    /** Future-dated readings per station (wrong station clock), for the panel on this page. */
    public function futureReadings()
    {
        try {
            $rows = SeismicFutureReadings::summary();
        } catch (\Throwable $e) {
            report($e);
            return response()->json(['message' => 'Could not read the seismic database.'], 500);
        }

        $manila = fn ($t) => \Carbon\Carbon::parse($t)->timezone('Asia/Manila')->format('M j, Y g:i A');
        return response()->json([
            'stations' => array_map(fn ($r) => [
                'station_id' => $r->station_id,
                'readings'   => (int) $r->readings,
                'first'      => $manila($r->first_time),
                'last'       => $manila($r->last_time),
            ], $rows),
            'can_delete' => session('role') === 'superAdmin',
        ]);
    }

    /** Deletes the future-dated readings (super admins only, like Delete All Logs). */
    public function deleteFutureReadings()
    {
        abort_unless(session('role') === 'superAdmin', 403);

        try {
            $deleted = SeismicFutureReadings::delete();
        } catch (\Throwable $e) {
            report($e);
            return response()->json(['message' => 'Could not delete the readings: ' . $e->getMessage()], 500);
        }

        Log::warning('Deleted future-dated seismic readings', ['deleted' => $deleted, 'user' => session('username')]);
        return response()->json(['deleted' => $deleted, 'message' => "Deleted {$deleted} future-dated reading(s)."]);
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