<?php

namespace App\Console\Commands;

use App\Services\Sentinel\SentinelReporter;
use Illuminate\Console\Command;

/**
 * Scheduled every minute (routes/console.php). Sends the status report to
 * Uplink Sentinel when it's due: on the configured interval, after a status
 * change, or when a retry is due. Does nothing while disabled.
 */
class SentinelPush extends Command
{
    protected $signature = 'sentinel:push';

    protected $description = 'Send the sensor status report to Uplink Sentinel when due (scheduled every minute).';

    public function handle(SentinelReporter $reporter): int
    {
        $this->line($reporter->tick());

        // Always succeed: a Sentinel problem must never look like an EMS failure.
        return self::SUCCESS;
    }
}
