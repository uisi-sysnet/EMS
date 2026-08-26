<?php

namespace App\Console\Commands;

use App\Http\Controllers\CameraController;
use App\Models\Camera;
use Illuminate\Console\Command;

/**
 * Re-pushes every enabled camera's RTSP source to mediamtx via
 * CameraController::syncOnvifStream(). Exists because mediamtx does not
 * currently retain API-added paths across its own restart on this setup
 * (see mediamtx.yml write permissions) — without this, every
 * ems-mediamtx restart silently drops all camera sources until someone
 * manually re-opens and re-saves each camera in the dashboard.
 *
 * Wire this into ems-mediamtx's systemd unit as an ExecStartPost so it
 * runs automatically every time the service (re)starts:
 *
 *   ExecStartPost=/bin/sh -c 'sleep 2 && /usr/bin/php8.3 /home/system/EMS/Dashboard/artisan cameras:resync'
 *
 * The short sleep gives mediamtx's own API listener (127.0.0.1:9997) a
 * moment to come up before this starts POSTing to it — its API starts
 * last among mediamtx's listeners per its own boot log.
 */
class ResyncCameras extends Command
{
    protected $signature = 'cameras:resync';

    protected $description = 'Re-push every enabled camera\'s RTSP source to mediamtx (e.g. after mediamtx restarts and loses its runtime-added paths).';

    public function handle(CameraController $controller): int
    {
        $cameras = Camera::where('enabled', true)->get();

        if ($cameras->isEmpty()) {
            $this->info('No enabled cameras to resync.');

            return self::SUCCESS;
        }

        $hadError = false;

        foreach ($cameras as $camera) {
            $this->line("Resyncing {$camera->name} ({$camera->slug}, channel {$camera->channel})...");

            $controller->syncOnvifStream($camera);
            $camera->refresh();

            if ($camera->last_status === 'error') {
                $hadError = true;
                $this->error("  -> {$camera->last_error}");
            } else {
                $this->info('  -> ok');
            }
        }

        return $hadError ? self::FAILURE : self::SUCCESS;
    }
}