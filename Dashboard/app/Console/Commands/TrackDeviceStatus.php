<?php

namespace App\Console\Commands;

use App\Http\Controllers\CameraController;
use App\Http\Controllers\DashboardController;
use App\Models\Camera;
use App\Models\DeviceStatusState;
use App\Models\SystemLog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

/**
 * Logs devices going offline and coming back online, as Device events on
 * the Logs page. Runs every minute (routes/console.php).
 *
 * A device is "up" while it is online or idle and "down" when offline,
 * using the dashboard's own status rules (DashboardController::
 * deviceStatusSnapshot()). Only changes are logged: the first time a
 * device is seen its status is recorded silently, so installing this on a
 * gateway with offline stations doesn't flood the log.
 */
class TrackDeviceStatus extends Command
{
    protected $signature = 'logs:track-device-status';

    protected $description = 'Log devices (stations, cameras, lead sensors) going offline or coming back online.';

    public function handle(DashboardController $dashboard): int
    {
        if (!Schema::connection('logs')->hasTable('service_logs')) {
            $this->warn('service_logs table not found; nothing to log to yet.');
            return self::SUCCESS;
        }

        $now = now();
        $states = DeviceStatusState::all()->keyBy('key');
        $changes = 0;

        foreach ($dashboard->deviceStatusSnapshot() as $device) {
            $current = $device['status'] === 'offline' ? 'down' : 'up';
            $state = $states->get($device['key']);

            if ($state === null) {
                DeviceStatusState::create(['key' => $device['key'], 'status' => $current, 'changed_at' => $now]);
                continue;
            }
            if ($state->status === $current) {
                continue;
            }

            $wasDownFor = $state->changed_at ? $state->changed_at->diffForHumans($now, true) : null;
            $detail = $device['detail'] ? " ({$device['detail']})" : '';

            if ($current === 'down') {
                $level = 'WARNING';
                $message = "{$device['type']} {$device['name']} went offline{$detail}.";
            } else {
                $level = 'INFO';
                $message = "{$device['type']} {$device['name']} is back online" . ($wasDownFor ? " after {$wasDownFor} offline" : '') . '.';
            }

            SystemLog::create([
                'created_at'  => $now,
                'service'     => 'dashboard',
                'level'       => $level,
                'logger_name' => 'device_status',
                'thread_name' => null,
                'message'     => $message,
                'category'    => 'device',
            ]);

            $state->update(['status' => $current, 'changed_at' => $now]);
            $changes++;
        }

        $this->resyncRecoveredCameras($now);

        $this->info("{$changes} device status change(s) logged.");
        return self::SUCCESS;
    }

    /**
     * A camera whose last stream sync failed (last_status = 'error') has no
     * live view until it's synced again. Once it answers pings, retry the
     * sync here instead of waiting for someone to press Refresh — at most
     * every 5 minutes per camera, so a camera with wrong credentials isn't
     * hammered every minute.
     */
    private function resyncRecoveredCameras(\Illuminate\Support\Carbon $now): void
    {
        $up = DeviceStatusState::where('key', 'like', 'camera:%')->where('status', 'up')->pluck('key')
            ->map(fn ($key) => (int) substr($key, strlen('camera:')));

        $cameras = Camera::where('enabled', true)
            ->where('last_status', 'error')
            ->whereIn('id', $up)
            ->where('updated_at', '<', $now->copy()->subMinutes(5))
            ->get();

        foreach ($cameras as $camera) {
            app(CameraController::class)->syncOnvifStream($camera);
            $camera->refresh();
            // A repeat of the same error leaves the row unchanged, so
            // updated_at wouldn't move and the 5-minute wait would not apply.
            $camera->touch();

            $ok = $camera->last_status !== 'error';
            SystemLog::create([
                'created_at'  => $now,
                'service'     => 'dashboard',
                'level'       => $ok ? 'INFO' : 'WARNING',
                'logger_name' => 'device_status',
                'thread_name' => null,
                'message'     => $ok
                    ? "Camera {$camera->name} live view restored (stream re-synced)."
                    : "Camera {$camera->name} is reachable but its live view can't start: {$camera->last_error}",
                'category'    => 'device',
            ]);
        }
    }
}
