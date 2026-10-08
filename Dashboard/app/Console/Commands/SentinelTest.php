<?php

namespace App\Console\Commands;

use App\Services\Sentinel\SentinelReporter;
use Illuminate\Console\Command;

/**
 * Manual connection test for Uplink Sentinel: sends one snapshot now and
 * prints Sentinel's response. Works while SENTINEL_EMS_ENABLED=false, so
 * the link can be checked before turning it on.
 *
 *   php artisan sentinel:test               send one report, show the response
 *   php artisan sentinel:test --dry-run     only print the JSON that would be sent
 *                                           (e.g. > sample.json for a curl test)
 */
class SentinelTest extends Command
{
    protected $signature = 'sentinel:test {--dry-run : Print the report JSON without sending it}';

    protected $description = 'Send one status report to Uplink Sentinel now and show the response.';

    public function handle(SentinelReporter $reporter): int
    {
        if ($this->option('dry-run')) {
            $this->output->writeln(json_encode($reporter->buildPayload(),
                JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            return self::SUCCESS;
        }

        if (!$reporter->configured()) {
            $this->error('Set SENTINEL_EMS_URL and SENTINEL_EMS_TOKEN in scripts/.env first.');
            return self::FAILURE;
        }

        $this->line('Sending to ' . config('sentinel.url') . ' ...');
        $result = $reporter->sendNow();

        $this->line('Units sent: ' . count($result['payload']['units'])
            . ' (' . ($result['payload']['overall']['message'] ?? '') . ')');
        $this->line('HTTP status: ' . ($result['code'] ?? 'no response'));
        $this->line('Response:');
        $this->output->writeln(is_array($result['body'])
            ? json_encode($result['body'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            : (string) ($result['body'] ?? '(none)'));
        $result['code'] === 202 ? $this->info($result['summary']) : $this->error($result['summary']);

        if (!config('sentinel.enabled')) {
            $this->warn('Scheduled reporting is off. Set SENTINEL_EMS_ENABLED=true in scripts/.env to turn it on.');
        }

        return $result['code'] === 202 ? self::SUCCESS : self::FAILURE;
    }
}
