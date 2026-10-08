<?php

/*
|--------------------------------------------------------------------------
| Uplink Sentinel (outbound status reporting)
|--------------------------------------------------------------------------
|
| Sends every sensor's status to Uplink Sentinel, our project-monitoring
| system (POST {url}, Bearer token). Sentinel only receives; it never calls
| back. See App\Services\Sentinel and `php artisan sentinel:test`.
|
| Set these in scripts/.env (also editable in the dashboard's Env Editor);
| values there override Dashboard/.env. Disabled by default.
|
*/

return [
    'enabled' => filter_var(env('SENTINEL_EMS_ENABLED', false), FILTER_VALIDATE_BOOLEAN),

    // e.g. http://SENTINEL_HOST:8090/api/ems/status
    'url' => env('SENTINEL_EMS_URL'),

    // Bearer token issued by Sentinel for this EMS link. Never logged.
    'token' => env('SENTINEL_EMS_TOKEN'),

    // Scheduled send interval. Status changes are also sent within ~1-2
    // minutes, whatever this is set to.
    'interval_minutes' => max(1, (int) env('SENTINEL_EMS_INTERVAL_MINUTES', 30)),

    // "system" name sent with every report.
    'system' => env('SENTINEL_EMS_SYSTEM', 'EMS-AQ'),

    'timeout_seconds' => 15,
];
