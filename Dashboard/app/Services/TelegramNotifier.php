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
     * Sends $jpegBytes as a photo, with an optional caption (Telegram
     * caption limit is 1024 chars — keep it short since the image itself
     * carries the detail). Same success/failure contract as send(): never
     * throws, returns false on any failure so callers can log and move on.
     */
    public function sendPhoto(string $jpegBytes, ?string $caption = null): bool
    {
        $settings = TelegramSetting::current();

        if (! $settings->isConfigured()) {
            Log::warning('Telegram photo skipped: bot token or chat ID not set.');
            return false;
        }

        try {
            // This host has an IPv6 address configured but no working route
            // to the outside world — connecting to api.telegram.org's AAAA
            // record fails instantly at the kernel level (ENETUNREACH), and
            // Guzzle's curl handler doesn't fall back to the working IPv4
            // address fast enough, instead stalling for the full timeout
            // with 0 bytes received. Forcing IPv4 here sidesteps the broken
            // route entirely rather than depending on fallback timing.
            $request = Http::timeout(20)
                ->withOptions(['curl' => [CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4]])
                ->attach('photo', $jpegBytes, 'system-status.jpg');

            $payload = ['chat_id' => $settings->chat_id];
            if (filled($caption)) {
                $payload['caption']    = $caption;
                $payload['parse_mode'] = 'HTML';
            }

            $response = $request->post("https://api.telegram.org/bot{$settings->botToken()}/sendPhoto", $payload);

            if ($response->failed()) {
                Log::error('Telegram sendPhoto failed', [
                    'status' => $response->status(),
                    'body'   => $response->body(),
                ]);
                self::logProblem("Telegram digest photo was not sent: HTTP {$response->status()} " . ($response->json('description') ?? ''));

                return false;
            }

            return true;
        } catch (\Throwable $e) {
            Log::error('Telegram sendPhoto exception', ['message' => $e->getMessage()]);
            self::logProblem('Telegram digest photo was not sent: ' . self::withoutToken($e->getMessage(), $settings->botToken()));

            return false;
        }
    }

    /**
     * Records a Telegram problem on the Logs page (logger "telegram"), so a
     * digest or alert that silently stopped going out shows up there and in
     * the notification bell instead of only in laravel.log. The same message
     * is recorded at most once every 30 minutes, since the alert check runs
     * every minute. Never throws.
     */
    public static function logProblem(string $message): void
    {
        try {
            $message = mb_substr($message, 0, 2000);
            $recent = \App\Models\SystemLog::where('logger_name', 'telegram')
                ->where('message', $message)
                ->where('created_at', '>', now()->subMinutes(30))
                ->exists();
            if (! $recent) {
                \App\Models\SystemLog::create([
                    'created_at'  => now(),
                    'service'     => 'dashboard',
                    'level'       => 'ERROR',
                    'logger_name' => 'telegram',
                    'thread_name' => null,
                    'message'     => $message,
                    'category'    => 'system',
                ]);
            }
        } catch (\Throwable $e) {
            report($e);
        }
    }

    public const UNREADABLE_TOKEN = 'Telegram is not sending: the saved bot token can\'t be decrypted because APP_KEY in Dashboard/.env has changed since it was saved. '
        . 'Re-enter the bot token on Settings > Telegram, or add the old key to Dashboard/.env as APP_PREVIOUS_KEYS.';

    /**
     * For the scheduled commands: true if Telegram can be used. When the
     * saved token can't be decrypted, says so on the Logs page and the
     * console instead of silently doing nothing.
     */
    public static function ready(TelegramSetting $settings, ?\Illuminate\Console\Command $console = null): bool
    {
        if ($settings->tokenUnreadable()) {
            self::logProblem(self::UNREADABLE_TOKEN);
            $console?->error(self::UNREADABLE_TOKEN);
            return false;
        }

        return $settings->isConfigured();
    }

    /** Connection errors quote the request URL, which contains the bot token. */
    private static function withoutToken(string $message, ?string $token): string
    {
        return filled($token) ? str_replace($token, '***', $message) : $message;
    }

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
            // Same broken-IPv6-route workaround as sendPhoto() above — see
            // that method's comment for why this is needed.
            $response = Http::timeout(10)
                ->withOptions(['curl' => [CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4]])
                ->post(
                    "https://api.telegram.org/bot{$settings->botToken()}/sendMessage",
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
                self::logProblem("Telegram alert was not sent: HTTP {$response->status()} " . ($response->json('description') ?? ''));

                return false;
            }

            return true;
        } catch (\Throwable $e) {
            Log::error('Telegram sendMessage exception', ['message' => $e->getMessage()]);
            self::logProblem('Telegram alert was not sent: ' . self::withoutToken($e->getMessage(), $settings->botToken()));

            return false;
        }
    }
}