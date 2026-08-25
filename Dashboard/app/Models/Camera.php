<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Camera extends Model
{
    protected $fillable = [
        'channel',
        'name', 
        'slug',
        'location',
        'ip_address', 
        'onvif_port', 
        'username', 
        'password',
        'onvif_profile_token', 
        'rtsp_uri',
        'device_type',
        'serial_number',
        'latitude',
        'longitude',
        'enabled', 
        'last_synced_at',
        'last_status',
        'last_error',
        'notes',
        'deleted_at', // boolean flag
    ];

    protected $casts = [
        'password' => 'encrypted',
        'enabled' => 'boolean',
        'deleted_at' => 'boolean',
        'last_synced_at' => 'datetime',
        'created_at' => 'datetime', // Explicit cast (optional, Laravel does this automatically)
        'updated_at' => 'datetime', // Explicit cast (optional, Laravel does this automatically)
    ];

    // Never let the password leak into array/JSON output (API responses, logs).
    protected $hidden = ['password'];

    protected static function booted(): void
    {
        static::creating(function (Camera $camera) {
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