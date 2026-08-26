<?php

namespace App\Http\Controllers;

use App\Models\Camera;
use App\Services\Mediamtx\MediaMtxClient;
use App\Services\Onvif\OnvifClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;
use Throwable;

class CameraController extends Controller
{
    /**
     * Display a listing of cameras.
     */
    public function index(): View
    {
        $cameras = Camera::orderBy('name')->get();

        return view('inventory.cameras', [
            'cameras' => $cameras,
        ]);
    }

    /**
     * Show the form for creating a new camera.
     */
    public function create(): View
    {
        return view('inventory.cameras.create');
    }

    /**
     * Store a newly created camera in storage.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'channel' => 'required|string|max:50',
            'name' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255|unique:cameras,slug',
            'location' => 'nullable|string|max:255',
            'ip_address' => 'required|string|max:255',
            'onvif_port' => 'required|integer|min:1|max:65535',
            'username' => 'required|string|max:255',
            'password' => 'required|string',
            'onvif_profile_token' => 'nullable|string|max:255',
            'rtsp_uri' => 'nullable|string',
            'device_type' => 'nullable|string|max:50',
            'serial_number' => 'nullable|string|max:50|unique:cameras,serial_number',
            'latitude' => 'nullable|numeric|min:-90|max:90',
            'longitude' => 'nullable|numeric|min:-180|max:180',
            'enabled' => 'boolean',
            'notes' => 'nullable|string',
        ]);

        // Set default values
        $validated['enabled'] = $request->boolean('enabled', true);
        $validated['onvif_port'] = $validated['onvif_port'] ?? 80;

        $camera = Camera::create($validated);

        $this->syncOnvifStream($camera);

        return redirect()
            ->route('inventory.cameras.index')
            ->with('success', "Camera '{$camera->name}' created successfully.");
    }

    /**
     * Show the form for editing the specified camera.
     * Returns JSON for modal or View for full page.
     */
    public function edit($id): JsonResponse
    {
        $camera = Camera::findOrFail($id);
        return response()->json($camera);
    }

    /**
     * Update the specified camera in storage.
     */
    public function update(Request $request, $id): RedirectResponse
    {
        $camera = Camera::findOrFail($id);

        $validated = $request->validate([
            'channel' => 'required|string|max:50',
            'name' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255|unique:cameras,slug,' . $camera->id,
            'location' => 'nullable|string|max:255',
            'ip_address' => 'required|string|max:255',
            'onvif_port' => 'required|integer|min:1|max:65535',
            'username' => 'required|string|max:255',
            'password' => 'nullable|string',
            'onvif_profile_token' => 'nullable|string|max:255',
            'rtsp_uri' => 'nullable|string',
            'device_type' => 'nullable|string|max:50',
            'serial_number' => 'nullable|string|max:50|unique:cameras,serial_number,' . $camera->id,
            'latitude' => 'nullable|numeric|min:-90|max:90',
            'longitude' => 'nullable|numeric|min:-180|max:180',
            'enabled' => 'boolean',
            'notes' => 'nullable|string',
        ]);

        // Only update password if provided
        if (empty($validated['password'])) {
            unset($validated['password']);
        }

        $validated['enabled'] = $request->boolean('enabled', true);

        $camera->update($validated);

        $this->syncOnvifStream($camera);

        return redirect()
            ->route('inventory.cameras.index')
            ->with('success', "Camera '{$camera->name}' updated successfully.");
    }

    /**
     * Remove the specified camera from storage.
     */
    public function destroy($id): RedirectResponse
    {
        $camera = Camera::findOrFail($id);

        $name = $camera->name;
        $slug = $camera->slug;

        $camera->delete();

        try {
            app(MediaMtxClient::class)->deletePath($slug);
        } catch (Throwable $e) {
            // Non-fatal: the camera row is already gone. Worst case a stale
            // mediamtx path lingers until the next refresh/edit overwrites it.
            report($e);
        }

        return redirect()
            ->route('inventory.cameras.index')
            ->with('success', "Camera '{$name}' deleted successfully.");
    }

    /**
     * Restore a soft-deleted camera.
     */
    public function restore($id): RedirectResponse
    {
        $camera = Camera::withTrashed()->findOrFail($id);
        $camera->restore();

        return redirect()
            ->route('inventory.cameras.index')
            ->with('success', "Camera '{$camera->name}' restored successfully.");
    }

    /**
     * Re-resolve a camera's ONVIF stream URI and push it to mediamtx.
     * Bound to the "Refresh" button on the cameras grid.
     */
    public function refresh($id): RedirectResponse
    {
        $camera = Camera::findOrFail($id);

        $this->syncOnvifStream($camera);
        $camera->refresh();

        return $camera->last_status === 'error'
            ? redirect()->route('inventory.cameras.index')
                ->with('error', "Camera '{$camera->name}': {$camera->last_error}")
            : redirect()->route('inventory.cameras.index')
                ->with('success', "Camera '{$camera->name}' refreshed.");
    }

    /**
     * Builds the RTSP URL mediamtx will actually connect to, with the
     * camera's credentials embedded (rtsp://user:pass@host:port/path).
     * Falls back to the unmodified URI if it can't be parsed rather than
     * risk mangling a working URL.
     */
    private function withRtspCredentials(string $uri, string $username, string $password): string
    {
        $parts = parse_url($uri);
        if ($parts === false || ! isset($parts['scheme'], $parts['host'])) {
            return $uri;
        }

        $authority = rawurlencode($username) . ':' . rawurlencode($password) . '@' . $parts['host'];
        if (isset($parts['port'])) {
            $authority .= ':' . $parts['port'];
        }

        return $parts['scheme'] . '://' . $authority
            . ($parts['path'] ?? '')
            . (isset($parts['query']) ? '?' . $parts['query'] : '');
    }

    /**
     * Talks to the camera over ONVIF to resolve its media profile and RTSP
     * stream URI, saves that onto the camera row, and pushes it to mediamtx
     * as a WebRTC-egress source path (keyed by the camera's slug — the same
     * slug the WHEP endpoint /cctv-stream/{slug}/whep is served under).
     *
     * Called from store()/update()/refresh() rather than left for the
     * frontend to trigger, since without this step mediamtx never has a
     * path to serve and every viewer request 404s/fails silently.
     */
    private function syncOnvifStream(Camera $camera): void
    {
        try {
            $onvif = new OnvifClient(
                host: $camera->ip_address,
                port: $camera->onvif_port,
                username: $camera->username,
                password: $camera->password,
            );

            $token = $camera->onvif_profile_token;
            if (! $token) {
                $profiles = $onvif->getProfiles();
                $token = $profiles[0]['token'];
            }

            $streamUri = $onvif->getStreamUri($token);

            $camera->forceFill([
                'onvif_profile_token' => $token,
                'rtsp_uri' => $streamUri,
                'last_status' => 'online',
                'last_error' => null,
            ])->save();

            // ONVIF's GetStreamUri deliberately returns a bare RTSP URL —
            // SOAP auth and RTSP-stream auth are separate per spec — but
            // the camera still expects RTSP-level Basic/Digest auth using
            // the same credentials. Without this, mediamtx connects with
            // no credentials at all and every pull attempt gets a 401.
            // Injected only here (not persisted) so the plaintext password
            // never lands in the rtsp_uri column or the edit() JSON payload.
            $authedUri = $this->withRtspCredentials($streamUri, $camera->username, $camera->password);

            app(MediaMtxClient::class)->upsertPath($camera->slug, $authedUri);
        } catch (Throwable $e) {
            $camera->forceFill([
                'last_status' => 'error',
                'last_error' => $e->getMessage(),
            ])->save();

            report($e);
        }
    }

    /**
     * Display the live view for cameras.
     */
    public function live()
    {
        $cameras = Camera::where('enabled', true)->orderBy('name')->get();

        return view('server.cameras-live', [
            'cameras'          => $cameras,
            'mediamtxReadUser' => config('services.mediamtx.read_user'),
            'mediamtxReadPass' => config('services.mediamtx.read_pass'),
        ]);
    }

    /**
     * Export cameras data.
     */
    public function export()
    {
        return redirect()
            ->route('inventory.cameras.index')
            ->with('error', 'Export functionality is coming soon.');
    }

    /**
     * Download import format template.
     */
    public function downloadFormat()
    {
        return redirect()
            ->route('inventory.cameras.index')
            ->with('error', 'Download format functionality is coming soon.');
    }

    /**
     * Import cameras from file.
     */
    public function import(Request $request): RedirectResponse
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv|max:5120',
        ]);

        return redirect()
            ->route('inventory.cameras.index')
            ->with('success', 'Import functionality is coming soon.');
    }
}