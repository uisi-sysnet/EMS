<?php

namespace App\Services;

use App\Models\TelegramSetting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Thin wrapper around Telegram's Bot API sendMessage endpoint. Reads the
 * bot token/chat ID from TelegramSetting (set via the in-app Settings >
 * Telegram page) rather than .env, so an admin can change or rotate them
 * without a redeploy.
 */
class TelegramNotifier
{
    /**
     * Sends $text (HTML parse mode — <b>, <i>, etc.) to the configured
     * chat. Returns true on success, false if Telegram isn't configured
     * or the API call fails. Callers (scheduled commands) should treat
     * false as "log it and move on to the next station/metric", not
     * throw — one bad send shouldn't abort an entire digest/check run.
     */
    public function send(string $text): bool
    {
        $settings = TelegramSetting::current();

        if (! $settings->isConfigured()) {
            Log::warning('Telegram notification skipped: bot token or chat ID not set.');
            return false;
        }

        try {
            $response = Http::timeout(10)->post(
                "https://api.telegram.org/bot{$settings->bot_token}/sendMessage",
                [
                    'chat_id'    => $settings->chat_id,
                    'text'       => $text,
                    'parse_mode' => 'HTML',
                ]
            );

            if ($response->failed()) {
                Log::error('Telegram sendMessage failed', [
                    'status' => $response->status(),
                    'body'   => $response->body(),
                ]);

                return false;
            }

            return true;
        } catch (\Throwable $e) {
            Log::error('Telegram sendMessage exception', ['message' => $e->getMessage()]);

            return false;
        }
    }
}