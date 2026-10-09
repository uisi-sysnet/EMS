<?php

namespace App\Services\Sentinel;

use App\Models\SentinelSetting;
use App\Models\SystemLog;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Sends the status snapshot (SentinelSnapshot) to Uplink Sentinel.
 *
 * tick() runs every minute (sentinel:push) and sends when:
 * - the scheduled interval (SENTINEL_EMS_INTERVAL_MINUTES) has passed, or
 * - any unit's status changed and stayed changed for about a minute
 *   (debounce, so a flapping sensor isn't reported on every blip).
 *
 * Failures:
 * - 429, 5xx, timeout, connection error: retry after 1, 2, 5, then every
 *   10 minutes. Nothing is queued: each retry sends a fresh snapshot.
 * - 401 / 403 (wrong token, link disabled, IP not allowed): wait 30 minutes.
 * - 400 / 422 (payload rejected): logged, not retried; the next scheduled
 *   send or status change sends a new snapshot.
 *
 * Every attempt is logged on the Logs page (service "dashboard", logger
 * "sentinel"). The token is never logged. Nothing here may throw into the
 * caller: a Sentinel problem must never affect the EMS.
 */
class SentinelReporter
{
    private const STATE_KEY = 'sentinel.state';
    private const DEBOUNCE_SECONDS = 55;   // the scheduler runs every 60s
    private const BACKOFF_MINUTES = [1, 2, 5, 10];
    private const AUTH_RETRY_MINUTES = 30;

    public function __construct(private SentinelSnapshot $snapshot)
    {
    }

    /** Link settings: Settings > Sentinel, else scripts/.env (SentinelSetting::effective()). */
    public function settings(): array
    {
        return SentinelSetting::effective();
    }

    public function configured(): bool
    {
        $s = $this->settings();
        return filled($s['url']) && filled($s['token']);
    }

    /** Scheduled entry point. Returns a one-line summary for the console. */
    public function tick(): string
    {
        try {
            $settings = $this->settings();
            if (!$settings['enabled']) {
                return "Sentinel reporting is disabled ({$settings['source']}).";
            }
            if (!$this->configured()) {
                return "Sentinel reporting is enabled but the Sentinel address or key is missing ({$settings['source']}).";
            }

            $state = Cache::get(self::STATE_KEY, []);
            $now = time();

            if (!empty($state['next_attempt_at']) && $now < $state['next_attempt_at']) {
                return 'Waiting to retry until ' . date('H:i:s', $state['next_attempt_at']) . '.';
            }

            $payload = $this->snapshot->build();
            $signature = $this->signature($payload);

            $intervalDue = empty($state['last_sent_at'])
                || $now - $state['last_sent_at'] >= 60 * (int) $settings['interval_minutes'];

            $changeDue = false;
            if (isset($state['last_sent_signature']) && $signature !== $state['last_sent_signature']) {
                if (($state['pending_signature'] ?? null) !== $signature) {
                    // New change: wait about a minute to see if it holds.
                    $state['pending_signature'] = $signature;
                    $state['pending_since'] = $now;
                } elseif ($now - ($state['pending_since'] ?? $now) >= self::DEBOUNCE_SECONDS) {
                    $changeDue = true;
                }
            } else {
                unset($state['pending_signature'], $state['pending_since']);
            }

            if (!$intervalDue && !$changeDue && empty($state['failures'])) {
                Cache::forever(self::STATE_KEY, $state);
                return isset($state['pending_signature'])
                    ? 'Status changed; sending in about a minute if it holds.'
                    : 'Nothing to send.';
            }

            $result = $this->send($payload, $changeDue && !$intervalDue ? 'status change' : 'scheduled');
            Cache::forever(self::STATE_KEY, $this->nextState($state, $result, $signature, $now));

            return $result['summary'];
        } catch (\Throwable $e) {
            report($e);
            return 'Sentinel report failed: ' . $e->getMessage();
        }
    }

    /**
     * Sends one snapshot now (sentinel:test). Works while disabled, so the
     * link can be tested before turning it on. Doesn't touch the schedule.
     *
     * @return array{code: ?int, body: mixed, summary: string, payload: array}
     */
    public function sendNow(): array
    {
        $payload = $this->snapshot->build();
        return $this->send($payload, 'manual test') + ['payload' => $payload];
    }

    /** After the link settings change: send on the next run, clear any backoff. */
    public function resetSchedule(): void
    {
        Cache::forget(self::STATE_KEY);
    }

    public function buildPayload(): array
    {
        return $this->snapshot->build();
    }

    /**
     * @return array{code: ?int, body: mixed, summary: string, outcome: string, retry_after: ?int}
     */
    private function send(array $payload, string $reason): array
    {
        $result = $this->doSend($payload, $reason);
        SentinelSetting::recordResult($result['code'], $result['summary'], $result['outcome'] === 'ok');

        return $result;
    }

    private function doSend(array $payload, string $reason): array
    {
        $settings = $this->settings();
        $units = count($payload['units']);
        $code = null;
        $body = null;
        $retryAfter = null;

        try {
            $response = Http::withToken((string) $settings['token'])
                ->acceptJson()
                ->connectTimeout((int) config('sentinel.timeout_seconds', 15))
                ->timeout((int) config('sentinel.timeout_seconds', 15))
                ->withBody(json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE),
                    'application/json; charset=utf-8')
                ->post((string) $settings['url']);

            $code = $response->status();
            $body = $response->json() ?? mb_substr($response->body(), 0, 500);
            $retryAfter = is_numeric($response->header('Retry-After')) ? (int) $response->header('Retry-After') : null;
        } catch (ConnectionException $e) {
            $error = $e->getMessage();
        } catch (\Throwable $e) {
            $error = $e->getMessage();
        }

        // A manual test doesn't schedule retries, so don't promise one.
        $manual = $reason === 'manual test';
        $retry = $manual ? '' : ' Retrying in ' . self::AUTH_RETRY_MINUTES . ' minutes.';

        // Never include the token: messages are built from the response only.
        if ($code === 202) {
            $warnings = (array) ($body['warnings'] ?? []);
            $summary = sprintf('Sentinel accepted %d unit(s) (%s): HTTP 202, matched %s, added %s, changes %s, unknown IDs %s.',
                $units, $reason,
                $this->count($body['matched'] ?? null), $this->count($body['added_devices'] ?? null),
                $this->count($body['changes'] ?? null), $this->count($body['unknown_ids'] ?? null));
            if ($warnings) {
                $summary .= ' Warnings: ' . mb_substr(implode(' | ', array_map(fn ($w) => is_scalar($w) ? (string) $w : json_encode($w), $warnings)), 0, 1500);
            }
            $this->log($warnings ? 'WARNING' : 'INFO', $summary);
            return compact('code', 'body', 'summary') + ['outcome' => 'ok', 'retry_after' => null];
        }

        if ($code === 400 || $code === 422) {
            $errors = $code === 422 ? json_encode($body['errors'] ?? $body, JSON_UNESCAPED_UNICODE) : 'body is not valid JSON';
            $summary = "Sentinel rejected the report ({$reason}): HTTP {$code}, " . mb_substr((string) $errors, 0, 1500) . ' Not retried; the EMS report format needs fixing.';
            $this->log('ERROR', $summary);
            return compact('code', 'body', 'summary') + ['outcome' => 'rejected', 'retry_after' => null];
        }

        if ($code === 401 || $code === 403) {
            $summary = $code === 401
                ? "Sentinel refused the report: HTTP 401, wrong key. Check the key on Settings > Sentinel." . $retry
                : "Sentinel refused the report: HTTP 403, the link is disabled in Sentinel or this gateway's IP isn't allowed." . $retry;
            $this->log('ERROR', $summary);
            return compact('code', 'body', 'summary') + ['outcome' => 'auth', 'retry_after' => null];
        }

        if ($code === 429 || ($code !== null && $code >= 500) || $code === null) {
            $what = $code === null ? 'could not reach Sentinel (' . mb_substr($error ?? 'unknown error', 0, 300) . ')' : "Sentinel answered HTTP {$code}";
            $summary = "Sentinel report ({$reason}) failed: {$what}." . ($manual ? '' : ' Will retry with backoff.');
            $this->log('WARNING', $summary);
            return compact('code', 'body', 'summary') + ['outcome' => 'retry', 'retry_after' => $retryAfter];
        }

        $summary = "Sentinel report ({$reason}) got an unexpected HTTP {$code}. Check the Sentinel IP address and port." . $retry;
        $this->log('ERROR', $summary);
        return compact('code', 'body', 'summary') + ['outcome' => 'auth', 'retry_after' => null];
    }

    private function nextState(array $state, array $result, string $signature, int $now): array
    {
        $state['last_attempt_at'] = $now;

        switch ($result['outcome']) {
            case 'ok':
            case 'rejected':
                // Done with this payload either way; don't resend it.
                $state['last_sent_at'] = $now;
                $state['last_sent_signature'] = $signature;
                unset($state['failures'], $state['next_attempt_at'], $state['pending_signature'], $state['pending_since']);
                break;
            case 'auth':
                $state['failures'] = ($state['failures'] ?? 0) + 1;
                $state['next_attempt_at'] = $now + 60 * self::AUTH_RETRY_MINUTES;
                break;
            default: // retry
                $state['failures'] = ($state['failures'] ?? 0) + 1;
                $minutes = self::BACKOFF_MINUTES[min($state['failures'], count(self::BACKOFF_MINUTES)) - 1];
                $state['next_attempt_at'] = $now + max(60 * $minutes, (int) ($result['retry_after'] ?? 0));
        }

        return $state;
    }

    /** Statuses only, so the debounce reacts to status changes, not readings. */
    private function signature(array $payload): string
    {
        $statuses = [];
        foreach ($payload['units'] as $unit) {
            $statuses[$unit['id']] = $unit['status'];
        }
        ksort($statuses);

        return md5(json_encode([$payload['overall']['status'] ?? null, $statuses]));
    }

    private function count($value): string
    {
        return is_array($value) ? (string) count($value) : (is_scalar($value) ? (string) $value : '-');
    }

    private function log(string $level, string $message): void
    {
        try {
            SystemLog::create([
                'created_at'  => now(),
                'service'     => 'dashboard',
                'level'       => $level,
                'logger_name' => 'sentinel',
                'thread_name' => null,
                'message'     => $message,
                'category'    => 'system',
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
