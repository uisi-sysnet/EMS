<?php

namespace App\Services\Sentinel;

use App\Http\Controllers\DashboardController;
use App\Models\SeismicStation;
use App\Models\Station;
use App\Models\WaterLevel;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Builds the status report for Uplink Sentinel: one unit per sensor.
 *
 * - Air quality: one unit per measurement a station reports (PM2.5, PM10,
 *   CO, temperature, ...), ID "<station MN>-<code>", e.g. "STN01-PM25".
 * - Seismic stations: "SEIS-<station id>".
 * - Water level stations: "WL-<station MN>" (enabled stations only).
 * - Cameras: "CAM-<id>" (enabled cameras only).
 *
 * Status follows the dashboard's rules: data within 2 minutes is online,
 * 2-3 minutes is "stale", older is "offline" for a silent station or
 * "no_data" for one measurement missing from a station that still reports.
 * Water level stations use their own reporting interval instead.
 *
 * Once a unit has been reported, its ID is remembered and sent every time
 * (as offline / no_data if it stops reporting), so Sentinel always gets
 * the full list and IDs never disappear.
 */
class SentinelSnapshot
{
    /** sensor_data column => [ID code, type, unit of measure] */
    public const AQ_MEASUREMENTS = [
        'pm25'             => ['PM25', 'PM2.5', 'µg/m³'],
        'pm10'             => ['PM10', 'PM10', 'µg/m³'],
        'tsp'              => ['TSP', 'TSP', 'µg/m³'],
        'ozone'            => ['O3', 'Ozone', 'µg/m³'],
        'carbon_monoxide'  => ['CO', 'Carbon Monoxide', 'mg/m³'],
        'sulfur_dioxide'   => ['SO2', 'Sulfur Dioxide', 'µg/m³'],
        'nitrogen_dioxide' => ['NO2', 'Nitrogen Dioxide', 'µg/m³'],
        'temperature'      => ['TEMP', 'Temperature', '°C'],
        'humidity'         => ['HUMIDITY', 'Humidity', '%'],
        'rain'             => ['RAIN', 'Rain', 'mm'],
        'noise'            => ['NOISE', 'Noise', 'dB'],
        'wind_speed'       => ['WIND_SPEED', 'Wind Speed', 'm/s'],
        'wind_direction'   => ['WIND_DIR', 'Wind Direction', '°'],
        'air_pressure'     => ['PRESSURE', 'Air Pressure', 'kPa'],
        'lead'             => ['LEAD', 'Lead', null],
        'lead_temperature' => ['LEAD_TEMP', 'Lead Sensor Temperature', '°C'],
    ];

    private const ONLINE_MINUTES = 2;
    private const STALE_MINUTES = 3;
    private const KNOWN_UNITS_KEY = 'sentinel.known_units';
    private const MAX_UNITS = 5000;

    private Carbon $now;

    public function __construct(private DashboardController $dashboard)
    {
    }

    /**
     * @return array{system: string, reported_at: string, overall: array, units: array}
     */
    public function build(): array
    {
        $this->now = now()->timezone('Asia/Manila');

        $units = array_merge(
            $this->guard(fn () => $this->airQualityUnits()),
            $this->guard(fn () => $this->seismicUnits()),
            $this->guard(fn () => $this->waterLevelUnits()),
            $this->guard(fn () => $this->cameraUnits()),
        );

        // Unit IDs must be distinct; keep the first of any duplicate.
        $units = array_values(collect($units)->unique('id')->all());
        $units = array_slice($units, 0, self::MAX_UNITS);

        $total = count($units);
        $online = count(array_filter($units, fn ($u) => $u['status'] === 'online'));
        $overall = match (true) {
            $total === 0       => ['status' => 'no_data', 'message' => 'No sensors configured'],
            $online === $total => ['status' => 'ok', 'message' => "{$online} of {$total} sensors reporting"],
            $online === 0      => ['status' => 'offline', 'message' => "0 of {$total} sensors reporting"],
            default            => ['status' => 'warning', 'message' => "{$online} of {$total} sensors reporting"],
        };

        return [
            'system'      => (string) config('sentinel.system', 'EMS-AQ'),
            'reported_at' => $this->now->toIso8601String(),
            'overall'     => $overall,
            'units'       => $units,
        ];
    }

    /** One failing source (e.g. a database not reachable) must not drop the others. */
    private function guard(callable $build): array
    {
        try {
            return $build();
        } catch (\Throwable $e) {
            report($e);
            return [];
        }
    }

    // ------------------------------------------------------------------
    // Air quality: one unit per measurement
    // ------------------------------------------------------------------

    private function airQualityUnits(): array
    {
        $stations = Station::query()
            ->where(fn ($q) => $q->whereNull('deleted')->orWhere('deleted', false))
            ->orderBy('station_mn')
            ->get();
        if ($stations->isEmpty()) {
            return [];
        }

        $columns = array_intersect(
            array_keys(self::AQ_MEASUREMENTS),
            Schema::connection('aq')->getColumnListing('sensor_data')
        );

        // Last 24 hours per station: newest time and value of each measurement.
        // data_time is stored as UTC without a time zone.
        $select = ['station_mn', 'MAX(data_time) AS last_at'];
        foreach ($columns as $c) {
            $select[] = "MAX(data_time) FILTER (WHERE {$c} IS NOT NULL) AS {$c}__at";
            $select[] = "(ARRAY_AGG({$c} ORDER BY data_time DESC) FILTER (WHERE {$c} IS NOT NULL))[1] AS {$c}__val";
        }
        $recent = collect(DB::connection('aq')->select(
            'SELECT ' . implode(', ', $select) . " FROM sensor_data
             WHERE data_time > (NOW() AT TIME ZONE 'UTC') - INTERVAL '24 hours'
             GROUP BY station_mn"
        ))->keyBy('station_mn');

        $known = Cache::get(self::KNOWN_UNITS_KEY, []);
        $units = [];

        foreach ($stations as $station) {
            $mn = $station->station_mn;
            $row = $recent->get($mn);

            $stationLast = $row?->last_at
                ? $this->utc($row->last_at)
                : $this->utc(DB::connection('aq')->table('sensor_data')->where('station_mn', $mn)->max('data_time'));
            $stationState = $this->ageStatus($stationLast, self::ONLINE_MINUTES, self::STALE_MINUTES);
            $stationLabel = $this->stationLabel($mn, $station->station_name);

            foreach ($columns as $c) {
                [$code, $type, $uom] = self::AQ_MEASUREMENTS[$c];
                $id = $this->id("{$mn}-{$code}");
                $at = $row ? $this->utc($row->{"{$c}__at"}) : null;

                // Only measurements this station has ever reported.
                if ($at === null && !isset($known[$id])) {
                    continue;
                }
                $known[$id] = true;

                if ($station->enabled === false) {
                    [$status, $message] = ['offline', 'Station is disabled in the EMS.'];
                } elseif ($stationState !== 'online' && $stationState !== 'stale') {
                    [$status, $message] = ['offline', $stationLast
                        ? 'No data from the station since ' . $this->human($stationLast) . '.'
                        : 'The station has never reported.'];
                } else {
                    $status = $this->ageStatus($at, self::ONLINE_MINUTES, self::STALE_MINUTES);
                    if ($status === 'offline') {
                        $status = 'no_data';
                    }
                    $message = match ($status) {
                        'no_data' => 'The station is reporting, but not this measurement'
                            . ($at ? ' since ' . $this->human($at) : '') . '.',
                        'stale'   => 'Last value ' . $this->human($at) . '.',
                        default   => null,
                    };
                }

                $value = $row?->{"{$c}__val"};
                $units[] = $this->unit($id, $status, $stationLabel, $type,
                    trim(($station->station_name ?: $mn) . " {$type}"), $message,
                    $value !== null ? ['value' => round((float) $value, 3), 'unit' => $uom] : null,
                    $at);
            }

            // A station that has never sent anything still shows up.
            if ($stationLast === null && !collect($units)->contains(fn ($u) => str_starts_with($u['id'], "{$mn}-"))) {
                $units[] = $this->unit($this->id("{$mn}-STATION"), 'no_data', $stationLabel, 'Air Quality Station',
                    $station->station_name ?: $mn, 'The station has never reported.', null, null);
            }
        }

        Cache::forever(self::KNOWN_UNITS_KEY, $known);

        return $units;
    }

    // ------------------------------------------------------------------
    // Seismic, water level, cameras: one unit per device
    // ------------------------------------------------------------------

    private function seismicUnits(): array
    {
        $stations = SeismicStation::orderBy('station_id')->get();
        if ($stations->isEmpty()) {
            return [];
        }

        $last = DB::connection('seismic')->table('station_metrics')
            ->select('station_id', DB::raw('MAX(time) AS last_at'))
            ->where('time', '>', now()->subDays(2))
            ->groupBy('station_id')
            ->pluck('last_at', 'station_id');

        return $stations->map(function ($s) use ($last) {
            $at = $this->utc($last[$s->station_id] ?? null);
            $status = $this->ageStatus($at, self::ONLINE_MINUTES, self::STALE_MINUTES);
            $name = $s->station_name ?: $s->station_id;

            return $this->unit($this->id("SEIS-{$s->station_id}"), $status, $this->stationLabel($s->station_id, $s->station_name),
                'Seismic', "{$name} Seismic", $this->silenceMessage($status, $at), null, $at);
        })->all();
    }

    private function waterLevelUnits(): array
    {
        $stations = WaterLevel::where('enabled', true)->orderBy('station_mn')->get();
        if ($stations->isEmpty() || !Schema::connection('water_level')->hasTable('sensor_data')) {
            return [];
        }

        $latest = collect(DB::connection('water_level')->select("
                SELECT DISTINCT ON (station_mn) station_mn, recorded_at, water_level
                FROM sensor_data
                WHERE recorded_at > NOW() - INTERVAL '7 days'
                ORDER BY station_mn, recorded_at DESC
            "))->keyBy('station_mn');

        return $stations->map(function ($s) use ($latest) {
            $row = $latest->get($s->station_mn);
            $at = $this->utc($row->recorded_at ?? null);
            // Same thresholds as the dashboard: online within one reporting
            // interval (+2 min for SMS delivery), stale until a second is missed.
            $interval = max(1, (int) ($s->report_interval_minutes ?? 15));
            $status = $this->ageStatus($at, $interval + 2, 2 * $interval + 2);
            $name = $s->station_name ?: $s->station_mn;

            return $this->unit($this->id("WL-{$s->station_mn}"), $status, $this->stationLabel($s->station_mn, $s->station_name),
                'Water Level', "{$name} Water Level", $this->silenceMessage($status, $at),
                isset($row->water_level) ? ['value' => round((float) $row->water_level, 2), 'unit' => 'm'] : null, $at);
        })->all();
    }

    private function cameraUnits(): array
    {
        // A camera's location is the name of the station it's installed at;
        // use that station's label so Sentinel files it in the same folder.
        $labels = [];
        foreach (Station::all(['station_mn', 'station_name']) as $s) {
            $labels[mb_strtolower(trim((string) ($s->station_name ?: $s->station_mn)))] = $this->stationLabel($s->station_mn, $s->station_name);
        }

        return $this->dashboard->cameraStatusList()
            ->filter(fn ($c) => $c->enabled)
            ->map(function ($c) use ($labels) {
                if ($c->status !== 'online') {
                    [$status, $message] = ['unreachable', "No answer on its stream or ONVIF port ({$c->ip})."];
                } elseif ($c->stream_error) {
                    [$status, $message] = ['fault', 'Reachable, but the live view is not set up: ' . $c->stream_error];
                } else {
                    [$status, $message] = ['online', null];
                }

                $station = $c->location ? ($labels[mb_strtolower(trim($c->location))] ?? $c->location) : null;

                return $this->unit($this->id("CAM-{$c->id}"), $status, $station, 'Camera',
                    $c->name ?: "Camera {$c->id}", $message, null, null);
            })
            ->values()
            ->all();
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    private function unit(string $id, string $status, ?string $station, ?string $type, ?string $name,
        ?string $message, ?array $reading, ?Carbon $lastDataAt): array
    {
        return array_filter([
            'id'           => $id,
            'status'       => $status,
            'station'      => $station !== null ? mb_substr($station, 0, 255) : null,
            'type'         => $type !== null ? mb_substr($type, 0, 50) : null,
            'name'         => $name !== null ? mb_substr($name, 0, 255) : null,
            'message'      => $message !== null ? mb_substr($message, 0, 2000) : null,
            'reading'      => $reading,
            'last_data_at' => $lastDataAt?->copy()->timezone('Asia/Manila')->toIso8601String(),
        ], fn ($v) => $v !== null);
    }

    /** online / stale / offline from the age of the latest data. */
    private function ageStatus(?Carbon $at, int $onlineMinutes, int $staleMinutes): string
    {
        if ($at === null) {
            return 'offline';
        }
        $minutes = $at->diffInMinutes($this->now, true);

        return $minutes <= $onlineMinutes ? 'online' : ($minutes <= $staleMinutes ? 'stale' : 'offline');
    }

    private function silenceMessage(string $status, ?Carbon $at): ?string
    {
        return match ($status) {
            'online'  => null,
            'stale'   => 'Last data ' . $this->human($at) . '.',
            default   => $at ? 'No data since ' . $this->human($at) . '.' : 'Has never reported.',
        };
    }

    /** Timestamps without a zone are UTC (as stored by the ingest services). */
    private function utc($value): ?Carbon
    {
        return $value ? Carbon::parse($value, 'UTC') : null;
    }

    private function human(Carbon $at): string
    {
        return $at->copy()->timezone('Asia/Manila')->format('M j, Y H:i');
    }

    private function stationLabel(string $code, ?string $name): string
    {
        return ($name && $name !== $code) ? "{$code} · {$name}" : $code;
    }

    private function id(string $id): string
    {
        return mb_substr($id, 0, 100);
    }
}
