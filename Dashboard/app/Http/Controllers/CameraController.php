<?php

namespace App\Http\Controllers;

use App\Models\Camera;
use App\Models\Station; 
use App\Services\Mediamtx\MediaMtxClient;
use App\Services\Onvif\OnvifClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;
use Throwable;
use App\Exports\CamerasFormatExport;
use App\Imports\CamerasImport;
use Maatwebsite\Excel\Facades\Excel;

class CameraController extends Controller
{
    /**
     * Display a listing of cameras.
     */
    public function index()
    {
        $cameras = Camera::orderBy('name', 'asc')->get();
        
        // Fetch unique locations from stations table
        $locations = Station::where('deleted', false)
            ->whereNotNull('location')
            ->where('location', '!=', '')
            ->distinct()
            ->pluck('location')
            ->sort()
            ->values()
            ->toArray();
        
        return view('inventory.cameras', compact('cameras', 'locations'));
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
     * Builds Dahua's documented native RTSP path directly
     * (rtsp://user:pass@host:554/cam/realmonitor?channel=N&subtype=0)
     * rather than trusting ONVIF's GetStreamUri for it. Dahua's ONVIF
     * stream-URI response is known to be unreliable across firmware
     * versions (wrong path/missing query params), while this native
     * pattern is stable and documented by the vendor — used here since
     * the fleet is single-vendor (Dahua). subtype=0 is the main/high
     * quality stream; subtype=1 is the lower-quality sub stream.
     *
     * Uses $camera->channel (not a hardcoded 1) — cameras sharing an
     * NVR/multi-input unit are distinguished by this field, and every
     * camera silently requesting channel 1 was why only one of two
     * added cameras ever actually connected.
     */
    private function dahuaRtspUri(Camera $camera, int $subtype = 0): string
    {
        $auth = rawurlencode($camera->username) . ':' . rawurlencode($camera->password);
        $channel = (int) ($camera->channel ?: 1);

        return "rtsp://{$auth}@{$camera->ip_address}:554/cam/realmonitor?channel={$channel}&subtype={$subtype}";
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
    public function syncOnvifStream(Camera $camera): void
    {
        try {
            // Force decryption to happen here, inside the try/catch, so a
            // stale/rotated APP_KEY produces a clear "couldn't decrypt the
            // stored password" error instead of masquerading as an ONVIF
            // auth failure lower down.
            try {
                $password = $camera->password;
            } catch (Throwable $e) {
                throw new \RuntimeException(
                    "Could not decrypt stored password (APP_KEY changed since it was saved?): {$e->getMessage()}",
                    previous: $e,
                );
            }

            // Still resolve the profile token via ONVIF (useful for
            // confirming the camera is actually reachable/authenticated,
            // and for any future PTZ/profile-specific work) — just don't
            // trust the stream URI it returns.
            try {
                $onvif = new OnvifClient(
                    host: $camera->ip_address,
                    port: $camera->onvif_port,
                    username: $camera->username,
                    password: $password,
                );

                $token = $camera->onvif_profile_token;
                if (! $token) {
                    $profiles = $onvif->getProfiles();
                    $token = $profiles[0]['token'] ?? null;

                    if (! $token) {
                        throw new \RuntimeException('ONVIF GetProfiles() returned no profiles.');
                    }
                }
            } catch (Throwable $e) {
                throw new \RuntimeException(
                    "ONVIF handshake with {$camera->ip_address}:{$camera->onvif_port} failed: {$e->getMessage()}",
                    previous: $e,
                );
            }

            // Bare (no-credentials) URI kept for display/debugging only —
            // this is what ONVIF reports, even though the actual mediamtx
            // source uses the native Dahua URL below.
            $bareStreamUri = "rtsp://{$camera->ip_address}:554/cam/realmonitor?channel={$camera->channel}&subtype=0";

            $camera->forceFill([
                'onvif_profile_token' => $token,
                'rtsp_uri' => $bareStreamUri,
                'last_status' => 'online',
                'last_error' => null,
            ])->save();

            $authedUri = $this->dahuaRtspUri($camera);

            // This is the step that actually keeps mediamtx's source
            // current. If it throws, the camera row above has already been
            // marked 'online', so explicitly flag it back to 'error' below
            // rather than leaving a misleadingly-healthy status on a path
            // mediamtx never got.
            try {
                app(MediaMtxClient::class)->upsertPath($camera->slug, $authedUri);
            } catch (Throwable $e) {
                throw new \RuntimeException(
                    "mediamtx upsertPath('{$camera->slug}') failed: {$e->getMessage()}",
                    previous: $e,
                );
            }
        } catch (Throwable $e) {
            $camera->forceFill([
                'last_status' => 'error',
                'last_error' => $e->getMessage(),
            ])->save();

            report($e);
        }
    }

    /**
     * Relay a PTZ continuous-move or stop command from the live view to
     * the camera over ONVIF. Bound to POST /cctv-stream/{slug}/ptz.
     */
    public function ptz(Request $request, string $slug): JsonResponse
    {
        $camera = Camera::where('slug', $slug)->firstOrFail();

        if ($camera->device_type !== 'PTZ') {
            return response()->json(['error' => 'Camera is not PTZ-capable.'], 422);
        }

        if (! $camera->onvif_profile_token) {
            return response()->json(['error' => 'Camera has no ONVIF profile token yet — refresh it first.'], 422);
        }

        $validated = $request->validate([
            'pan' => 'required_without:stop|numeric|between:-1,1',
            'tilt' => 'required_without:stop|numeric|between:-1,1',
            'zoom' => 'nullable|numeric|between:-1,1',
            'stop' => 'sometimes|boolean',
        ]);

        try {
            $onvif = new OnvifClient(
                host: $camera->ip_address,
                port: $camera->onvif_port,
                username: $camera->username,
                password: $camera->password,
            );

            if ($request->boolean('stop')) {
                $onvif->stop($camera->onvif_profile_token);
            } else {
                $onvif->continuousMove(
                    $camera->onvif_profile_token,
                    (float) ($validated['pan'] ?? 0),
                    (float) ($validated['tilt'] ?? 0),
                    (float) ($validated['zoom'] ?? 0),
                );
            }

            return response()->json(['ok' => true]);
        } catch (Throwable $e) {
            report($e);

            return response()->json(['error' => $e->getMessage()], 502);
        }
    }

    public function getLocations()
    {
        $locations = Station::where('deleted', false)
            ->whereNotNull('location')
            ->distinct()
            ->pluck('location')
            ->sort()
            ->values();
        
        return response()->json($locations);
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
        try {
            return Excel::download(new CamerasExport(), 'cameras_export_' . date('Y-m-d_Hi') . '.xlsx');
        } catch (\Exception $e) {
            return redirect()
                ->route('inventory.cameras.index')
                ->with('error', 'Export failed: ' . $e->getMessage());
        }
    }

    /**
     * Download import format template.
     */
    public function downloadFormat()
    {
        try {
            return Excel::download(new CamerasFormatExport(), 'camera_import_format.xlsx');
        } catch (\Exception $e) {
            return redirect()
                ->route('inventory.cameras.index')
                ->with('error', 'Unable to download format: ' . $e->getMessage());
        }
    }

    /**
     * Import cameras from file.
     */
    public function import(Request $request): RedirectResponse
    {
        try {
            $request->validate([
                'file' => 'required|file|mimes:xlsx,xls,csv|max:5120',
            ]);

            $import = new CamerasImport();
            Excel::import($import, $request->file('file'));

            $imported = $import->getImportedCount();
            $errors = $import->getErrors();

            // Build success message
            if ($imported > 0) {
                $message = "Successfully imported {$imported} camera(s).";
                
                if (!empty($errors)) {
                    $errorMessage = " Errors: " . implode('; ', array_slice($errors, 0, 5));
                    if (count($errors) > 5) {
                        $errorMessage .= " and " . (count($errors) - 5) . " more errors.";
                    }
                    
                    return redirect()
                        ->route('inventory.cameras.index')
                        ->with('warning', $message . $errorMessage);
                }

                return redirect()
                    ->route('inventory.cameras.index')
                    ->with('success', $message);
            } else {
                $errorMsg = "No cameras were imported. " . (!empty($errors) ? implode('; ', array_slice($errors, 0, 3)) : "Please check your file format.");
                
                return redirect()
                    ->route('inventory.cameras.index')
                    ->with('error', $errorMsg);
            }

        } catch (\Maatwebsite\Excel\Validators\ValidationException $e) {
            $failures = $e->failures();
            $errorMessages = [];
            foreach ($failures as $failure) {
                $errorMessages[] = "Row {$failure->row()}: " . implode(', ', $failure->errors());
            }
            
            return redirect()
                ->route('inventory.cameras.index')
                ->with('error', 'Import failed: ' . implode('; ', array_slice($errorMessages, 0, 3)));
                
        } catch (\Exception $e) {
            return redirect()
                ->route('inventory.cameras.index')
                ->with('error', 'Import failed: ' . $e->getMessage());
        }
    }
}