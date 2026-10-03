<?php

namespace App\Http\Controllers;

use App\Models\WaterLevel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class WaterLevelStationController extends Controller
{
    public function index()
    {
        $hasReadings = Schema::connection('water_level')->hasTable('sensor_data');

        $stations = WaterLevel::orderBy('station_mn')
            ->when($hasReadings, fn ($q) => $q->withCount('sensorData'))
            ->get();

        $deletedStations = WaterLevel::onlyTrashed()
            ->when($hasReadings, fn ($q) => $q->withCount('sensorData'))
            ->orderBy('station_mn')
            ->get();

        return view('inventory.water_level', compact('stations', 'deletedStations'));
    }

    public function store(Request $request)
    {
        $this->prepareSimNumber($request);

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
            'lead_ip'             => ['nullable', 'ip', 'max:15',
                                      Rule::unique('water_level.stations', 'lead_ip')],
            'lead_port'           => ['nullable', 'integer', 'min:1', 'max:65535'],
            'lead_slave'          => ['nullable', 'integer', 'min:1', 'max:255'],
            'sim_number'          => ['nullable', 'required_without:lead_ip', 'regex:/^\+[1-9]\d{9,14}$/',
                                      Rule::unique('water_level.stations', 'sim_number')],
            'report_interval_minutes' => ['nullable', 'integer', 'min:1', 'max:1440'],
        ], $this->messages());

        $validated['enabled'] = $request->boolean('enabled', true);
        $validated['report_interval_minutes'] ??= 15;

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
            'sim_number'          => $station->sim_number,
            'report_interval_minutes'  => $station->report_interval_minutes,
            'applied_interval_minutes' => $station->applied_interval_minutes,
            'interval_pending'         => $station->intervalPending(),
        ]);
    }

    public function update(Request $request, $station_mn)
    {
        $station = WaterLevel::where('station_mn', $station_mn)->firstOrFail();
        $this->prepareSimNumber($request);

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
            'lead_ip'             => ['nullable', 'ip', 'max:15',
                                      Rule::unique('water_level.stations', 'lead_ip')
                                          ->ignore($station->id)],
            'lead_port'           => ['nullable', 'integer', 'min:1', 'max:65535'],
            'lead_slave'          => ['nullable', 'integer', 'min:1', 'max:255'],
            'sim_number'          => ['nullable', 'required_without:lead_ip', 'regex:/^\+[1-9]\d{9,14}$/',
                                      Rule::unique('water_level.stations', 'sim_number')
                                          ->ignore($station->id)],
            'report_interval_minutes' => ['nullable', 'integer', 'min:1', 'max:1440'],
        ], $this->messages());

        $validated['enabled'] = $request->boolean('enabled', true);
        $validated['report_interval_minutes'] ??= $station->report_interval_minutes ?? 15;

        $station->fill($validated);
        // A new interval or SIM must be (re)sent to the sensor right away;
        // water_level_gsm.py picks up stations whose interval_sent_at is null.
        if ($station->isDirty(['report_interval_minutes', 'sim_number'])) {
            $station->interval_sent_at = null;
        }
        $station->save();

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
     * Normalise the SIM number before validation so 0917..., 63917... and
     * +63 917-... all become +63917..., the form water_level_gsm.py matches
     * incoming SMS senders against. An empty IP becomes null (GSM stations).
     */
    protected function prepareSimNumber(Request $request): void
    {
        $sim = preg_replace('/[\s\-().]/', '', (string) $request->input('sim_number', ''));

        if ($sim === '') {
            $sim = null;
        } elseif (preg_match('/^0(9\d{9})$/', $sim, $m)) {   // Philippine mobile, local format
            $sim = '+63' . $m[1];
        } elseif (preg_match('/^63\d{10}$/', $sim)) {
            $sim = '+' . $sim;
        }
        $request->merge(['sim_number' => $sim]);

        if (trim((string) $request->input('lead_ip', '')) === '') {
            $request->merge(['lead_ip' => null]);
        }
    }

    private function messages(): array
    {
        return [
            'station_mn.unique'           => 'This Station MN already exists (including deleted stations).',
            'lead_ip.unique'              => 'This IP Address is already assigned to another station (including deleted stations).',
            'sim_number.required_without' => 'Enter a SIM number or an IP address.',
            'sim_number.regex'            => 'Enter the SIM number as 09XXXXXXXXX or in international format (+63...).',
            'sim_number.unique'           => 'This SIM number is already assigned to another station (including deleted stations).',
        ];
    }

    /**
     * How many readings a station has in IOT_water_level.sensor_data, for
     * the delete confirmation. Reports zero if the table isn't there yet.
     */
    public function checkData($station_mn)
    {
        $exists = WaterLevel::withTrashed()
            ->where('station_mn', $station_mn)
            ->exists();

        $dataCount = Schema::connection('water_level')->hasTable('sensor_data')
            ? DB::connection('water_level')->table('sensor_data')->where('station_mn', $station_mn)->count()
            : 0;

        return response()->json([
            'hasData'   => $dataCount > 0,
            'dataCount' => $dataCount,
            'exists'    => $exists,
        ]);
    }
}