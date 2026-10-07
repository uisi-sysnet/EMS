<?php

/*
|--------------------------------------------------------------------------
| Log retention (php artisan logs:prune, runs hourly)
|--------------------------------------------------------------------------
|
| How many days of each log to keep. Older entries are deleted. Set a value
| to 0 to keep that log forever. Override in Dashboard/.env.
|
*/

return [
    // Logs page > Logs tab (service_logs: services, device events)
    'service_logs_days' => (int) env('LOG_RETENTION_DAYS', 30),

    // API Logs page (api_request_logs: one row per API request)
    'api_logs_days' => (int) env('API_LOG_RETENTION_DAYS', 30),

    // Logs page > Audit Log tab (activity_log: who did what)
    'audit_logs_days' => (int) env('AUDIT_LOG_RETENTION_DAYS', 365),

    // Plain (non-TimescaleDB) tables are deleted in batches; stop after this
    // many seconds per run and continue on the next run, so a first cleanup
    // of millions of rows never locks the database for long.
    'max_seconds_per_run' => (int) env('LOG_PRUNE_MAX_SECONDS', 240),
];
