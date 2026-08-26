<?php

namespace App\Services\Mediamtx;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Talks to mediamtx's local REST API (127.0.0.1 only — never exposed
 * publicly) to create/update/remove WebRTC-egress paths as cameras are
 * added, edited, or removed in the dashboard.
 */
class MediaMtxClient
{
    private string $baseUrl;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('services.mediamtx.api_url', 'http://127.0.0.1:9997'), '/');
    }

    public function upsertPath(string $name, string $sourceRtspUrl): void
    {
        // Read auth is handled globally via authInternalUsers in
        // mediamtx.yml (matching MEDIAMTX_READ_USER/READ_PASS) — mediamtx
        // rejects any path config that also sets legacy readUser/readPass
        // once authInternalUsers is in play, so those are intentionally
        // left out here.
        $payload = [
            'source' => $sourceRtspUrl,
            'sourceOnDemand' => true,
        ];

        $response = Http::timeout(5)->post("{$this->baseUrl}/v3/config/paths/add/{$name}", $payload);

        // Path already exists — /add rejects it, /replace updates it in
        // place with a full config replacement (there is no /edit endpoint
        // in mediamtx's v3 API; that was a bug — it 404s).
        if ($response->status() === 400) {
            $response = Http::timeout(5)->post("{$this->baseUrl}/v3/config/paths/replace/{$name}", $payload);
        }

        if ($response->failed()) {
            throw new RuntimeException(
                "mediamtx rejected path '{$name}': HTTP {$response->status()} {$response->body()}"
            );
        }
    }

    public function deletePath(string $name): void
    {
        $response = Http::timeout(5)->delete("{$this->baseUrl}/v3/config/paths/delete/{$name}");

        // 404 just means it was never created (e.g. camera never successfully synced).
        if ($response->failed() && $response->status() !== 404) {
            throw new RuntimeException("Failed to remove mediamtx path '{$name}': HTTP {$response->status()}");
        }
    }
}