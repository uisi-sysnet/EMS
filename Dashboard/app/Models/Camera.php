<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Camera extends Model
{
    protected $fillable = [
        'name', 'location', 'channel',
        'ip_address', 'onvif_port', 'username', 'password',
        'onvif_profile_token', 'rtsp_uri',
        'device_type', 'serial_number', 'latitude', 'longitude',
        'enabled', 'notes',
    ];

    protected $casts = [
        'password' => 'encrypted',
        'enabled' => 'boolean',
        'last_synced_at' => 'datetime',
    ];

    // Never let the password leak into array/JSON output (API responses, logs).
    protected $hidden = ['password'];

    protected static function booted(): void
    {
        static::creating(function (Camera $camera) {
            // 'channel' is NOT NULL with no DB default on this table — until
            // there's a real per-channel UI, default every camera to "1"
            // rather than let inserts fail.
            if (empty($camera->channel)) {
                $camera->channel = '1';
            }

            if (empty($camera->slug)) {
                $base = Str::slug($camera->name) ?: 'camera';
                $slug = $base;
                $i = 1;
                while (static::where('slug', $slug)->exists()) {
                    $slug = $base . '-' . (++$i);
                }
                $camera->slug = $slug;
            }
        });
    }

    /**
     * The RTSP URL mediamtx should actually pull from, with credentials
     * injected. Built at use-time from rtsp_uri (which ONVIF returns
     * without credentials) so the password isn't stored a second time
     * embedded in a URL string.
     */
    public function sourceUrl(): ?string
    {
        if (! $this->rtsp_uri) {
            return null;
        }

        $parts = parse_url($this->rtsp_uri);
        if ($parts === false || ! isset($parts['host'])) {
            return null;
        }

        $userinfo = rawurlencode($this->username) . ':' . rawurlencode($this->password);
        $port = isset($parts['port']) ? ':' . $parts['port'] : '';
        $path = $parts['path'] ?? '';
        $query = isset($parts['query']) ? '?' . $parts['query'] : '';

        return "rtsp://{$userinfo}@{$parts['host']}{$port}{$path}{$query}";
    }
}