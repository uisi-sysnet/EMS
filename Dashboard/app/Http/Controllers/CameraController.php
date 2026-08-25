<?php

namespace App\Http\Controllers;

use App\Models\Camera;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

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

        return redirect()
            ->route('cameras.index')
            ->with('success', "Camera '{$camera->name}' created successfully.");
    }

    /**
     * Show the form for editing the specified camera.
     */
    public function edit(Camera $camera): View
    {
        return view('inventory.cameras.edit', [
            'camera' => $camera,
        ]);
    }

    /**
     * Update the specified camera in storage.
     */
    public function update(Request $request, Camera $camera): RedirectResponse
    {
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

        return redirect()
            ->route('cameras.index')
            ->with('success', "Camera '{$camera->name}' updated successfully.");
    }

    /**
     * Remove the specified camera from storage.
     */
    public function destroy(Camera $camera): RedirectResponse
    {
        $name = $camera->name;
        
        $camera->delete(); // This uses Laravel's soft delete if enabled on the model
        
        return redirect()
            ->route('inventory.cameras.index')
            ->with('success', "Camera '{$name}' deleted successfully.");
    }

    /**
     * Restore a soft-deleted camera.
     */
    public function restore($id): RedirectResponse
    {
        $camera = Camera::where('deleted_at', true)->findOrFail($id);
        $camera->update(['deleted_at' => false]);

        return redirect()
            ->route('cameras.index')
            ->with('success', "Camera '{$camera->name}' restored successfully.");
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
        // Implementation for export functionality
        // This would typically generate a CSV or Excel file
        return redirect()
            ->route('cameras.index')
            ->with('error', 'Export functionality is coming soon.');
    }

    /**
     * Download import format template.
     */
    public function downloadFormat()
    {
        // Implementation for downloading import template
        // This would typically generate a sample CSV or Excel file
        return redirect()
            ->route('cameras.index')
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

        // Implementation for import functionality
        return redirect()
            ->route('cameras.index')
            ->with('success', 'Import functionality is coming soon.');
    }
}