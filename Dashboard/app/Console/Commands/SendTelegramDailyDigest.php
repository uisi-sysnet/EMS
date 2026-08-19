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
 * the current Asia/Manila time matches that slot's configured time AND
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
    protected $signature = 'telegram:daily-digest';

    protected $description = 'Send the morning/afternoon Telegram digest image, if either is due right now.';

    public function handle(DashboardController $dashboard, TelegramNotifier $telegram): int
    {
        $settings = TelegramSetting::current();

        if (! $settings->isConfigured()) {
            return self::SUCCESS;
        }

        $now = now('Asia/Manila');

        $this->maybeSend($settings, $telegram, $dashboard, $now,
            enabled: $settings->morning_digest_enabled,
            time: $settings->morning_digest_time,
            lastSentDate: $settings->morning_digest_last_sent_date,
            lastSentColumn: 'morning_digest_last_sent_date',
            label: 'Morning Digest',
        );

        $this->maybeSend($settings, $telegram, $dashboard, $now,
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
        bool $enabled,
        string $time,
        ?\Carbon\Carbon $lastSentDate,
        string $lastSentColumn,
        string $label,
    ): void {
        if (! $enabled) {
            return;
        }

        if ($now->format('H:i') !== $time) {
            return;
        }

        if ($lastSentDate?->isSameDay($now)) {
            return; // this slot already sent today
        }

        $image   = $dashboard->buildReportImageJpeg();
        $caption = "📊 <b>{$label}</b> — {$now->format('M j, Y g:i A')}";

        $telegram->sendPhoto($image, $caption);

        $settings->update([$lastSentColumn => $now->toDateString()]);
    }
}