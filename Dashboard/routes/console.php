<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('telegram:daily-digest')->everyMinute();
Schedule::command('telegram:check-alerts')->everyMinute();
Schedule::command('logs:track-device-status')->everyMinute()->withoutOverlapping();
Schedule::command('logs:prune')->hourly()->withoutOverlapping();
// Uplink Sentinel status report: decides itself whether a send is due
// (interval, status change, retry). In the background so a slow Sentinel
// (15s timeout) never delays the other scheduled tasks.
Schedule::command('sentinel:push')->everyMinute()->withoutOverlapping(5)->runInBackground();