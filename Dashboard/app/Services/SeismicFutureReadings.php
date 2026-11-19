<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * Seismic readings dated in the future (a station with a wrong clock, e.g.
 * 2036). They make the station look online until that date and show the
 * wrong "latest" time. seismic_mqtt.py now refuses them; this finds and
 * deletes the ones already stored. Same 10-minute allowance for clock drift
 * as the ingester (FUTURE_LIMIT_SEC), measured against the database clock.
 */
class SeismicFutureReadings
{
    private const AHEAD = "now() + interval '10 minutes'";

    /** Per station: how many future-dated readings, and the earliest and latest of those dates. */
    public static function summary(): array
    {
        return DB::connection('seismic')->select(
            'SELECT station_id, count(*) AS readings, min(time) AS first_time, max(time) AS last_time
             FROM station_metrics WHERE time > ' . self::AHEAD . '
             GROUP BY station_id ORDER BY station_id'
        );
    }

    /** Deletes every future-dated reading; returns how many were deleted. */
    public static function delete(): int
    {
        return DB::connection('seismic')->delete('DELETE FROM station_metrics WHERE time > ' . self::AHEAD);
    }
}
