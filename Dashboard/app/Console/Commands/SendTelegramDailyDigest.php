<?php

namespace App\Console\Commands;

use App\Http\Controllers\DashboardController;
use App\Models\TelegramSetting;
use App\Services\TelegramNotifier;
use Illuminate\Console\Command;

/**
 * Fires the configured daily digest. Register this to run every minute
 * (see routes/console.php or App\Console\Kernel::schedule()) — it only
 * actually sends once the current Asia/Manila time matches the
 * admin-configured daily_digest_time AND today's digest hasn't already
 * gone out, so it's safe for the underlying cron to invoke
 * `schedule:run` every minute without risking a duplicate send.
 */
class SendTelegramDailyDigest extends Command
{
    protected $signature = 'telegram:daily-digest';

    protected $description = 'Send the scheduled daily Telegram digest (station status + system health), if one is due right now.';

    public function handle(DashboardController $dashboard, TelegramNotifier $telegram): int
    {
        $settings = TelegramSetting::current();

        if (! $settings->daily_digest_enabled || ! $settings->isConfigured()) {
            return self::SUCCESS;
        }

        $now = now('Asia/Manila');

        if ($now->format('H:i') !== $settings->daily_digest_time) {
            return self::SUCCESS;
        }

        if ($settings->last_digest_sent_date?->isSameDay($now)) {
            return self::SUCCESS; // already sent today
        }

        $snapshot = $dashboard->telegramSnapshot();
        $telegram->send($this->formatMessage($snapshot, $now));

        $settings->update(['last_digest_sent_date' => $now->toDateString()]);

        return self::SUCCESS;
    }

    private function formatMessage(array $snapshot, \Carbon\Carbon $now): string
    {
        $aq = $snapshot['airQualityCounts'];
        $sm = $snapshot['seismicCounts'];
        $h  = $snapshot['health'];

        $dot = fn (string $status) => match ($status) {
            'good', 'online'  => '🟢',
            'warning', 'idle' => '🟡',
            default           => '🔴',
        };

        return implode("\n", [
            "📊 <b>Daily System Digest</b> — {$now->format('M j, Y g:i A')}",
            '',
            '<b>Stations</b>',
            "{$dot($aq['offline'] > 0 ? 'critical' : 'good')} Air Quality: {$aq['online']} online, {$aq['idle']} idle, {$aq['offline']} offline",
            "{$dot($sm['offline'] > 0 ? 'critical' : 'good')} Seismic: {$sm['online']} online, {$sm['idle']} idle, {$sm['offline']} offline",
            '',
            '<b>System Health</b>',
            "{$dot($h['cpu']['status'])} CPU: {$h['cpu']['percent']}%",
            "{$dot($h['memory']['status'])} Memory: {$h['memory']['percent']}%",
            "{$dot($h['storage']['status'])} Storage: {$h['storage']['percent_free']}% free",
        ]);
    }
}