<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TelegramSetting extends Model
{
    protected $fillable = [
        'bot_token',
        'chat_id',
        'daily_digest_enabled',
        'daily_digest_time',
        'last_digest_sent_date',
        'offline_alert_enabled',
        'health_alert_enabled',
    ];

    protected $casts = [
        // Encrypted/decrypted transparently using the app's APP_KEY, so
        // the token is never stored or logged in plaintext.
        'bot_token'             => 'encrypted',
        'daily_digest_enabled'  => 'boolean',
        'offline_alert_enabled' => 'boolean',
        'health_alert_enabled'  => 'boolean',
        'last_digest_sent_date' => 'date',
    ];

    /**
     * This is a single-row settings table by design — one Telegram bot
     * per deployment. Always go through this instead of ::first(), so
     * callers get a real (if freshly-created, all-disabled) row instead
     * of null on a fresh install.
     */
    public static function current(): self
    {
        return static::firstOrCreate([], [
            'daily_digest_enabled'  => false,
            'daily_digest_time'     => '08:00',
            'offline_alert_enabled' => false,
            'health_alert_enabled'  => false,
        ]);
    }

    public function isConfigured(): bool
    {
        return filled($this->bot_token) && filled($this->chat_id);
    }
}