<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

/**
 * Uplink Sentinel link settings, edited on Settings > Sentinel. One row per
 * deployment, like TelegramSetting.
 *
 * effective() is what the reporter uses: these settings once a Sentinel
 * address and key have been saved here, otherwise the SENTINEL_EMS_*
 * values from scripts/.env (config/sentinel.php).
 */
class SentinelSetting extends Model
{
    use \App\Models\Concerns\ForgetsUnreadableSecrets;

    public const PATH = '/api/ems/status';

    protected $fillable = [
        'enabled', 'host', 'port', 'use_https', 'token', 'interval_minutes', 'system_name',
        'last_attempt_at', 'last_success_at', 'last_status_code', 'last_result',
    ];

    protected $casts = [
        // Encrypted with APP_KEY; never shown again once saved.
        'token'            => 'encrypted',
        'enabled'          => 'boolean',
        'use_https'        => 'boolean',
        'port'             => 'integer',
        'interval_minutes' => 'integer',
        'last_attempt_at'  => 'datetime',
        'last_success_at'  => 'datetime',
    ];

    protected $hidden = ['token'];

    public static function current(): self
    {
        return static::firstOrCreate([], [
            'enabled' => false, 'port' => 8090, 'use_https' => false,
            'interval_minutes' => 30, 'system_name' => 'EMS-AQ',
        ]);
    }

    public function isConfigured(): bool
    {
        return filled($this->host) && filled($this->key());
    }

    /**
     * The saved key, or null if none is saved or it can't be decrypted
     * (APP_KEY changed since it was saved; reading $this->token directly
     * would throw "The MAC is invalid").
     */
    public function key(): ?string
    {
        try {
            return $this->token;
        } catch (\Illuminate\Contracts\Encryption\DecryptException $e) {
            return null;
        }
    }

    public function keySaved(): bool
    {
        return filled($this->getRawOriginal('token'));
    }

    public function keyUnreadable(): bool
    {
        return $this->keySaved() && $this->key() === null;
    }

    public function url(): ?string
    {
        if (!filled($this->host)) {
            return null;
        }
        $host = str_contains($this->host, ':') && !str_starts_with($this->host, '[') ? "[{$this->host}]" : $this->host;

        return ($this->use_https ? 'https' : 'http') . "://{$host}:{$this->port}" . self::PATH;
    }

    /**
     * @return array{source: string, enabled: bool, url: ?string, token: ?string, interval_minutes: int, system: string}
     */
    public static function effective(): array
    {
        try {
            $row = Schema::hasTable('sentinel_settings') ? static::first() : null;
        } catch (\Throwable $e) {
            $row = null;
        }

        if ($row && $row->isConfigured()) {
            return [
                'source'           => 'settings page',
                'enabled'          => (bool) $row->enabled,
                'url'              => $row->url(),
                'token'            => $row->key(),
                'interval_minutes' => max(1, (int) $row->interval_minutes),
                'system'           => $row->system_name ?: 'EMS-AQ',
            ];
        }

        return [
            'source'           => 'scripts/.env',
            'enabled'          => (bool) config('sentinel.enabled'),
            'url'              => config('sentinel.url'),
            'token'            => config('sentinel.token'),
            'interval_minutes' => max(1, (int) config('sentinel.interval_minutes', 30)),
            'system'           => (string) config('sentinel.system', 'EMS-AQ'),
        ];
    }

    /** Stores the latest send result for the settings page (best effort). */
    public static function recordResult(?int $code, string $summary, bool $ok): void
    {
        try {
            if (!Schema::hasTable('sentinel_settings')) {
                return;
            }
            $row = static::current();
            $row->forceFill([
                'last_attempt_at'  => now(),
                'last_status_code' => $code,
                'last_result'      => mb_substr($summary, 0, 4000),
            ] + ($ok ? ['last_success_at' => now()] : []))->save();
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
