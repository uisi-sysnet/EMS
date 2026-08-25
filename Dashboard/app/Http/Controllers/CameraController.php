<?php

namespace App\Http\Controllers;

use App\Models\Camera;
use App\Services\Mediamtx\MediaMtxClient;
use App\Services\Onvif\OnvifClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class CameraController extends Controller
{
    public function index()
    {
        return view('server.cameras', [
            'cameras' => Camera::orderBy('name')->get(),
            'mediamtxReadUser' => config('services.mediamtx.read_user'),
            'mediamtxReadPass' => config('services.mediamtx.read_pass'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'location' => 'nullable|string|max:150',
            'ip_address' => 'required|ip',
            'onvif_port' => 'required|integer|min:1|max:65535',
            'username' => 'required|string|max:100',
            'password' => 'required|string|max:200',
            'notes' => 'nullable|string|max:1000',
        ]);

        $camera = Camera::create($validated);

        $this->sync($camera);

        return back()->with('status', "Camera '{$camera->name}' added.");
    }

    public function update(Request $request, Camera $camera): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'location' => 'nullable|string|max:150',
            'ip_address' => 'required|ip',
            'onvif_port' => 'required|integer|min:1|max:65535',
            'username' => 'required|string|max:100',
            'password' => 'nullable|string|max:200', // blank = keep existing password
            'notes' => 'nullable|string|max:1000',
        ]);

        if (empty($validated['password'])) {
            unset($validated['password']);
        }
        $validated['enabled'] = $request->boolean('enabled');

        $camera->update($validated);

        $this->sync($camera);

        return back()->with('status', "Camera '{$camera->name}' updated.");
    }

    public function destroy(Camera $camera): RedirectResponse
    {
        try {
            (new MediaMtxClient())->deletePath($camera->slug);
        } catch (Throwable $e) {
            Log::channel('services')->warning('Failed to remove mediamtx path on camera delete', [
                'camera' => $camera->slug,
                'error' => $e->getMessage(),
            ]);
        }

        $camera->delete();

        return back()->with('status', 'Camera removed.');
    }

    /**
     * Re-resolve the RTSP stream URI over ONVIF and push it to mediamtx.
     * Runs automatically on create/update, and is exposed as a manual
     * "Refresh" button for when a camera's network settings change.
     */
    public function refresh(Camera $camera): RedirectResponse
    {
        $this->sync($camera);

        return back()->with('status', "'{$camera->name}' re-synced.");
    }

    private function sync(Camera $camera): void
    {
        try {
            $onvif = new OnvifClient(
                $camera->ip_address,
                $camera->onvif_port,
                $camera->username,
                $camera->password,
            );

            $profiles = $onvif->getProfiles();
            $profileToken = $profiles[0]['token']; // primary profile — usually the main high-res stream
            $rtspUri = $onvif->getStreamUri($profileToken);

            $camera->forceFill([
                'onvif_profile_token' => $profileToken,
                'rtsp_uri' => $rtspUri,
                'last_synced_at' => now(),
                'last_status' => 'online',
                'last_error' => null,
            ])->save();

            $sourceUrl = $camera->sourceUrl();
            if ($sourceUrl) {
                (new MediaMtxClient())->upsertPath($camera->slug, $sourceUrl);
            }
        } catch (Throwable $e) {
            $camera->forceFill([
                'last_status' => 'error',
                'last_error' => $e->getMessage(),
                'last_synced_at' => now(),
            ])->save();

            Log::channel('services')->error('Camera ONVIF sync failed', [
                'camera' => $camera->slug,
                'error' => $e->getMessage(),
            ]);
        }
    }
}