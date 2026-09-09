<?php

namespace App\Console\Commands;

use App\Http\Controllers\DashboardController;
use App\Models\TelegramAlertState;
use App\Models\TelegramSetting;
use App\Services\TelegramNotifier;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

/**
 * Runs frequently (every minute — see scheduler registration) and sends a
 * Telegram message only on a STATE CHANGE: a station going offline (or
 * coming back), or a health metric crossing into/out of critical. It
 * never re-sends for a station or metric that's already in the state it
 * was in last time this ran, so a station stuck offline for hours
 * doesn't spam the chat every minute.
 */
class CheckTelegramAlerts extends Command
{
    protected $signature = 'telegram:check-alerts';

    protected $description = 'Send Telegram alerts for station offline/online transitions and critical system health.';

    public function handle(DashboardController $dashboard, TelegramNotifier $telegram): int
    {
        $settings = TelegramSetting::current();

        if (! $settings->isConfigured()) {
            return self::SUCCESS;
        }

        $snapshot = $dashboard->telegramSnapshot();

        if ($settings->offline_alert_enabled) {
            $this->checkStations($snapshot['airQualityData'], 'Air Quality', $telegram);
            $this->checkStations($snapshot['seismicData'], 'Seismic', $telegram);
        }

        if ($settings->health_alert_enabled) {
            $this->checkHealth($snapshot['health'], $telegram);
        }

        return self::SUCCESS;
    }

    private function checkStations(Collection $stations, string $type, TelegramNotifier $telegram): void
    {
        foreach ($stations as $station) {
            // buildDashboardData() returns plain objects with 'station_mn'
            // (air quality) or 'station_id' (seismic) as the identifier,
            // and 'station' as the display name — never 'id' or
            // 'station_name'. Reading those nonexistent properties used to
            // silently return null for every station, which collapsed
            // every station of a given type onto the SAME alert-state key
            // ("station:Air Quality:" / "station:Seismic:") and broke
            // transition tracking entirely. Pick whichever identifier the
            // object actually has instead.
            $identifier = $station->station_mn ?? $station->station_id ?? null;

            // Escaped because the message is sent with parse_mode=HTML —
            // an unescaped '<', '>' or '&' in the station name would make
            // Telegram reject the whole message with a 400.
            $name = htmlspecialchars($station->station ?? "Station {$identifier}", ENT_QUOTES);
            $key  = "station:{$type}:{$identifier}";

            $this->notifyOnTransition(
                key: $key,
                currentStatus: $station->status, // 'online' | 'idle' | 'offline'
                telegram: $telegram,
                onEnter: [
                    'offline' => "🔴 <b>{$type} station offline</b>\n{$name} has stopped reporting.",
                    'idle'    => "🟡 <b>{$type} station idle</b>\n{$name} hasn't sent a reading in a while.",
                ],
                onRecoverFrom: [
                    'offline' => "🟢 <b>{$type} station back online</b>\n{$name} is reporting again.",
                    'idle'    => "🟢 <b>{$type} station back online</b>\n{$name} is reporting again.",
                ],
            );
        }
    }

    private function checkHealth(array $health, TelegramNotifier $telegram): void
    {
        $metrics = [
            'cpu'     => ['label' => 'CPU usage', 'value' => "{$health['cpu']['percent']}%", 'status' => $health['cpu']['status']],
            'memory'  => ['label' => 'Memory usage', 'value' => "{$health['memory']['percent']}%", 'status' => $health['memory']['status']],
            'storage' => ['label' => 'Storage free', 'value' => "{$health['storage']['percent_free']}%", 'status' => $health['storage']['status']],
        ];

        foreach ($metrics as $metricKey => $m) {
            $this->notifyOnTransition(
                key: "health:{$metricKey}",
                currentStatus: $m['status'], // 'good' | 'warning' | 'critical'
                telegram: $telegram,
                onEnter: [
                    'critical' => "🔴 <b>{$m['label']} critical</b>\nCurrently at {$m['value']}.",
                ],
                onRecoverFrom: [
                    'critical' => "🟢 <b>{$m['label']} back to normal</b>\nCurrently at {$m['value']}.",
                ],
            );
        }
    }

    /**
     * Loads the last known status for $key, sends onEnter[$currentStatus]
     * if we just transitioned INTO that status, or onRecoverFrom[$prev]
     * if we just transitioned OUT of a status being watched for recovery
     * — then persists $currentStatus so the next run has an accurate
     * "last status" to compare against. If a send was attempted and
     * failed, the old status is left in place instead, so the same
     * transition is detected and retried on the next run rather than
     * being silently dropped. On the very first run for a given key (no
     * row yet), it just records the baseline status without sending
     * anything — otherwise every station/metric would fire a spurious
     * alert the moment this feature is turned on.
     */
    private function notifyOnTransition(string $key, string $currentStatus, TelegramNotifier $telegram, array $onEnter, array $onRecoverFrom): void
    {
        $state = TelegramAlertState::firstOrNew(['key' => $key]);
        $previousStatus = $state->exists ? $state->last_status : null;

        // Defaults to "ok" when no send was actually attempted (no prior
        // state, or the transition isn't one we message on), so those
        // cases still persist the baseline/current status as before.
        $sent = true;

        if ($previousStatus !== null && $previousStatus !== $currentStatus) {
            if (isset($onEnter[$currentStatus])) {
                $sent = $telegram->send($onEnter[$currentStatus]);
            } elseif (isset($onRecoverFrom[$previousStatus])) {
                $sent = $telegram->send($onRecoverFrom[$previousStatus]);
            }
        }

        if (! $sent) {
            // Leave last_status as the old value so next run sees the
            // same transition again and retries the notification,
            // instead of silently swallowing this alert forever.
            return;
        }

        $state->last_status = $currentStatus;
        $state->last_notified_at = now();
        $state->save();
    }
}