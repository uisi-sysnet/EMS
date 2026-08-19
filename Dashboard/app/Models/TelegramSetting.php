<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TelegramSetting extends Model
{
    protected $fillable = [
        'bot_token',
        'chat_id',
        'morning_digest_enabled',
        'morning_digest_time',
        'morning_digest_last_sent_date',
        'afternoon_digest_enabled',
        'afternoon_digest_time',
        'afternoon_digest_last_sent_date',
        'offline_alert_enabled',
        'health_alert_enabled',
    ];

    protected $casts = [
        // Encrypted/decrypted transparently using the app's APP_KEY, so
        // the token is never stored or logged in plaintext.
        'bot_token'                       => 'encrypted',
        'morning_digest_enabled'          => 'boolean',
        'morning_digest_last_sent_date'   => 'date',
        'afternoon_digest_enabled'        => 'boolean',
        'afternoon_digest_last_sent_date' => 'date',
        'offline_alert_enabled'           => 'boolean',
        'health_alert_enabled'            => 'boolean',
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
            'morning_digest_enabled'   => false,
            'morning_digest_time'      => '08:00',
            'afternoon_digest_enabled' => false,
            'afternoon_digest_time'    => '17:00',
            'offline_alert_enabled'    => false,
            'health_alert_enabled'     => false,
        ]);
    }

    public function isConfigured(): bool
    {
        return filled($this->bot_token) && filled($this->chat_id);
    }
}