<?php

namespace App\Console\Commands;

use App\Http\Controllers\DashboardController;
use App\Models\TelegramSetting;
use App\Services\TelegramNotifier;
use Illuminate\Console\Command;

/**
 * Fires the morning and/or afternoon digest, whichever is due right now.
 * Register this to run every minute (see routes/console.php or
 * App\Console\Kernel::schedule()) — each slot only actually sends once
 * the current Asia/Manila time has reached that slot's configured time
 * (catching up for up to CATCH_UP_HOURS if that minute was missed) AND
 * that slot hasn't already sent today, so it's safe for the underlying
 * cron to invoke `schedule:run` every minute without risking a duplicate
 * send, and the two slots track their own "already sent today" state
 * independently.
 *
 * The digest is the same JPEG image the dashboard's "Download Image"
 * button produces (see DashboardController::buildReportImageJpeg()),
 * posted as a Telegram photo — not a separate text summary that could
 * drift from what the dashboard shows.
 */
class SendTelegramDailyDigest extends Command
{
    protected $signature = 'telegram:daily-digest
        {--force : Skip the scheduled-time and already-sent-today checks and send both digests immediately — for testing}';

    protected $description = 'Send the morning/afternoon Telegram digest image, if either is due right now.';

    /** A digest missed at its time is still sent up to this many hours later. */
    private const CATCH_UP_HOURS = 2;

    public function handle(DashboardController $dashboard, TelegramNotifier $telegram): int
    {
        $settings = TelegramSetting::current();
        $force    = (bool) $this->option('force');

        if (! $settings->isConfigured()) {
            $this->warn('Telegram bot token / chat ID not configured — nothing sent.');
            return self::SUCCESS;
        }

        $now = now('Asia/Manila');

        $this->maybeSend($settings, $telegram, $dashboard, $now, $force,
            enabled: $settings->morning_digest_enabled,
            time: $settings->morning_digest_time,
            lastSentDate: $settings->morning_digest_last_sent_date,
            lastSentColumn: 'morning_digest_last_sent_date',
            label: 'Morning Digest',
        );

        $this->maybeSend($settings, $telegram, $dashboard, $now, $force,
            enabled: $settings->afternoon_digest_enabled,
            time: $settings->afternoon_digest_time,
            lastSentDate: $settings->afternoon_digest_last_sent_date,
            lastSentColumn: 'afternoon_digest_last_sent_date',
            label: 'Afternoon Digest',
        );

        return self::SUCCESS;
    }

    private function maybeSend(
        TelegramSetting $settings,
        TelegramNotifier $telegram,
        DashboardController $dashboard,
        \Carbon\Carbon $now,
        bool $force,
        bool $enabled,
        string $time,
        ?\Carbon\Carbon $lastSentDate,
        string $lastSentColumn,
        string $label,
    ): void {
        if (! $enabled) {
            if ($force) {
                $this->line("{$label}: skipped — not enabled in settings.");
            }
            return;
        }

        if (! $force) {
            if ($lastSentDate?->isSameDay($now)) {
                return; // this slot already sent today
            }

            // Due from the configured time until CATCH_UP_HOURS later. This
            // used to require the exact minute, so a scheduler run that was
            // late, skipped or failed in that one minute lost the whole
            // day's digest. Since it's only marked sent on success, a failed
            // send is retried every minute within the window. substr guards
            // against a TIME column returning "HH:MM:SS".
            $slot = $now->copy()->setTimeFromTimeString(substr($time, 0, 5));
            if ($now->lt($slot) || $now->gte($slot->copy()->addHours(self::CATCH_UP_HOURS))) {
                return;
            }
        }

        try {
            $image = $dashboard->buildReportImageJpeg();
        } catch (\Throwable $e) {
            // Recorded on the Logs page first: the log file may not be
            // writable by the scheduler's user, so report() could throw.
            TelegramNotifier::logProblem("{$label} was not sent: building the report image failed: {$e->getMessage()}");
            rescue(fn () => report($e), null, false);
            $this->error("{$label}: building the report image failed: {$e->getMessage()}");
            return;
        }

        $caption = "📊 <b>{$label}</b> — {$now->format('M j, Y g:i A')}";

        $ok = $telegram->sendPhoto($image, $caption);

        if ($force) {
            $this->line($ok
                ? "{$label}: sent."
                : "{$label}: FAILED — check storage/logs/laravel.log for the Telegram API response.");
        }

        // Forced test sends don't count as "today's digest" — otherwise
        // testing at 10am would suppress the real 8am/2pm scheduled send
        // for the rest of the day. And only mark it sent if it actually
        // succeeded — otherwise a transient Telegram API failure would
        // get recorded as "sent" and this slot would just stay silent
        // for the rest of the day instead of retrying next minute.
        if (! $force && $ok) {
            $settings->update([$lastSentColumn => $now->toDateString()]);
        }
    }
}