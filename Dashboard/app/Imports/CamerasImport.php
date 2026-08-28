<?php

namespace App\Imports;

use App\Models\Camera;
use App\Services\Mediamtx\MediaMtxClient;
use App\Services\Onvif\OnvifClient;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\SkipsFailures;
use Maatwebsite\Excel\Validators\Failure;
use Illuminate\Support\Str;
use Throwable;

class CamerasImport implements ToModel, WithHeadingRow, WithValidation, SkipsOnFailure
{
    use SkipsFailures;

    private $rowCount = 0;
    private $errors = [];
    private $importedCount = 0;

    /**
     * @param array $row
     * @return \Illuminate\Database\Eloquent\Model|null
     */
    public function model(array $row): ?\Illuminate\Database\Eloquent\Model  // ← Add this return type
    {
        $this->rowCount++;

        // Skip if required fields are missing
        if (empty($row['name']) || empty($row['ip_address'])) {
            $this->errors[] = "Row {$this->rowCount}: Missing required fields (name, ip_address)";
            return null;
        }

        // Generate slug if not provided
        $slug = !empty($row['slug']) ? $row['slug'] : Str::slug($row['name']);

        // Check for duplicate slug
        if (Camera::where('slug', $slug)->exists()) {
            $slug = $slug . '-' . uniqid();
        }

        // Check for duplicate serial number
        if (!empty($row['serial_number']) && Camera::where('serial_number', $row['serial_number'])->exists()) {
            $this->errors[] = "Row {$this->rowCount}: Serial number '{$row['serial_number']}' already exists";
            return null;
        }

        $camera = new Camera([
            'channel' => $row['channel'] ?? '1',
            'name' => $row['name'],
            'slug' => $slug,
            'location' => $row['location'] ?? null,
            'ip_address' => $row['ip_address'],
            'onvif_port' => isset($row['onvif_port']) ? (int) $row['onvif_port'] : 80,
            'username' => $row['username'] ?? '',
            'password' => $row['password'] ?? '',
            'onvif_profile_token' => $row['onvif_profile_token'] ?? null,
            'rtsp_uri' => $row['rtsp_uri'] ?? null,
            'device_type' => $row['device_type'] ?? 'IP Camera',
            'serial_number' => $row['serial_number'] ?? null,
            'latitude' => isset($row['latitude']) && $row['latitude'] !== '' ? (float) $row['latitude'] : null,
            'longitude' => isset($row['longitude']) && $row['longitude'] !== '' ? (float) $row['longitude'] : null,
            'notes' => $row['notes'] ?? null,
            
            // Default values for commented columns
            'enabled' => true, // Default to enabled
            'last_synced_at' => null, // Will be set when synced
            'last_status' => 'unknown', // Default status
            'last_error' => null, // No error initially
            'deleted_at' => null, // Not deleted
        ]);

        // Try to sync with ONVIF to get profile token and status
        try {
            $this->syncCamera($camera);
        } catch (Throwable $e) {
            // Keep the camera with default values, just log the error
            \Log::warning("Camera '{$camera->name}' imported but ONVIF sync failed: " . $e->getMessage());
            $camera->last_status = 'error';
            $camera->last_error = 'Import: ' . $e->getMessage();
        }

        $this->importedCount++;
        return $camera;
    }
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'ip_address' => 'required|string|max:255',
            'channel' => 'nullable|string|max:50',
            'onvif_port' => 'nullable|integer|min:1|max:65535',
            'username' => 'nullable|string|max:255',
            'password' => 'nullable|string',
            'serial_number' => 'nullable|string|max:50',
            'latitude' => 'nullable|numeric|min:-90|max:90',
            'longitude' => 'nullable|numeric|min:-180|max:180',
        ];
    }

    public function customValidationMessages()
    {
        return [
            'name.required' => 'Name is required for each camera.',
            'ip_address.required' => 'IP address is required for each camera.',
            'latitude.min' => 'Latitude must be between -90 and 90.',
            'latitude.max' => 'Latitude must be between -90 and 90.',
            'longitude.min' => 'Longitude must be between -180 and 180.',
            'longitude.max' => 'Longitude must be between -180 and 180.',
        ];
    }

    private function syncCamera(Camera $camera): void
    {
        try {
            $password = $camera->password;
            
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

            $camera->forceFill([
                'onvif_profile_token' => $token,
                'last_status' => 'online',
                'last_error' => null,
                'last_synced_at' => now(),
            ])->save();

            // Try to sync with MediaMtx
            try {
                app(MediaMtxClient::class)->upsertPath(
                    $camera->slug,
                    $this->buildRtspUri($camera)
                );
            } catch (Throwable $e) {
                // MediaMtx sync failed but camera is online
                \Log::warning("MediaMtx sync failed for camera '{$camera->name}': " . $e->getMessage());
            }

        } catch (Throwable $e) {
            throw new \RuntimeException(
                "ONVIF handshake with {$camera->ip_address}:{$camera->onvif_port} failed: {$e->getMessage()}",
                previous: $e,
            );
        }
    }

    private function buildRtspUri(Camera $camera): string
    {
        $auth = rawurlencode($camera->username) . ':' . rawurlencode($camera->password);
        $channel = (int) ($camera->channel ?: 1);

        return "rtsp://{$auth}@{$camera->ip_address}:554/cam/realmonitor?channel={$channel}&subtype=0";
    }

    public function getRowCount(): int
    {
        return $this->rowCount;
    }

    public function getImportedCount(): int
    {
        return $this->importedCount;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function onFailure(Failure ...$failures)
    {
        foreach ($failures as $failure) {
            $this->errors[] = "Row {$failure->row()}: " . implode(', ', $failure->errors());
        }
    }
}