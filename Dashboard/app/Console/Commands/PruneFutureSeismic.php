<?php

namespace App\Console\Commands;

use App\Services\SeismicFutureReadings;
use Illuminate\Console\Command;

class PruneFutureSeismic extends Command
{
    protected $signature = 'seismic:prune-future {--dry-run : Only show what would be deleted}';

    protected $description = 'Delete seismic readings dated in the future (a station with a wrong clock, e.g. 2036)';

    public function handle(): int
    {
        $rows = SeismicFutureReadings::summary();
        if (! $rows) {
            $this->info('No seismic readings are dated in the future.');
            return self::SUCCESS;
        }

        $this->table(['Station', 'Readings', 'Earliest date', 'Latest date'], array_map(
            fn ($r) => [$r->station_id, number_format($r->readings), $r->first_time, $r->last_time], $rows));

        if ($this->option('dry-run')) {
            $this->info('Dry run: nothing deleted.');
            return self::SUCCESS;
        }

        $deleted = SeismicFutureReadings::delete();
        $this->info("Deleted {$deleted} future-dated seismic reading(s).");

        return self::SUCCESS;
    }
}
