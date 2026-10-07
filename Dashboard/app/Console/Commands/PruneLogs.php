<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Deletes logs older than the retention set in config/log_retention.php,
 * so the log tables stop growing forever (73 million rows on one gateway
 * made every page slow). Runs hourly (routes/console.php).
 *
 * service_logs and api_request_logs are TimescaleDB hypertables on most
 * gateways: whole old chunks are dropped with drop_chunks(), which is
 * instant. What's left (plain tables without TimescaleDB, activity_log, and
 * the newest partly-expired chunk) is deleted in batches by id with a time
 * limit per run; whatever is left continues next run.
 */
class PruneLogs extends Command
{
    protected $signature = 'logs:prune {--dry-run : Only show how many rows would be deleted}';

    protected $description = 'Delete logs older than the configured retention (service, API and audit logs).';

    private const BATCH = 20000;

    private float $deadline;

    public function handle(): int
    {
        $this->deadline = microtime(true) + max(30, (int) config('log_retention.max_seconds_per_run'));

        $this->prune('logs', 'service_logs', (int) config('log_retention.service_logs_days'));
        $this->prune('api', 'api_request_logs', (int) config('log_retention.api_logs_days'));
        $this->prune('pgsql', 'activity_log', (int) config('log_retention.audit_logs_days'));

        return self::SUCCESS;
    }

    private function prune(string $connection, string $table, int $days): void
    {
        if ($days <= 0) {
            $this->line("{$table}: kept forever (retention 0).");
            return;
        }

        try {
            $db = DB::connection($connection);
            if (!$db->getSchemaBuilder()->hasTable($table)) {
                return;
            }
        } catch (\Throwable $e) {
            $this->warn("{$table}: database not reachable, skipped ({$e->getMessage()}).");
            return;
        }

        $cutoff = now()->subDays($days);

        if ($this->option('dry-run')) {
            $n = $db->table($table)->where('created_at', '<', $cutoff)->count();
            $this->line("{$table}: {$n} row(s) older than {$days} days would be deleted.");
            return;
        }

        try {
            if ($this->isHypertable($db, $table)) {
                $chunks = $db->select('SELECT drop_chunks(?::regclass, older_than => ?::timestamptz) AS c', [$table, $cutoff]);
                $this->line("{$table}: dropped " . count($chunks) . " chunk(s) older than {$days} days.");
                // drop_chunks only removes chunks entirely older than the
                // cutoff; the rest of the boundary chunk goes below.
            }

            $deleted = 0;
            if ($db->getSchemaBuilder()->hasColumn($table, 'id')) {
                do {
                    // Batches by id: short transactions, no long locks. (Not
                    // ctid — on a hypertable each chunk has its own ctids.)
                    $n = $db->affectingStatement(
                        "DELETE FROM {$table} WHERE created_at < ? AND id IN (SELECT id FROM {$table} WHERE created_at < ? LIMIT " . self::BATCH . ')',
                        [$cutoff, $cutoff]
                    );
                    $deleted += $n;
                } while ($n > 0 && microtime(true) < $this->deadline);
            } else {
                $n = $db->table($table)->where('created_at', '<', $cutoff)->delete();
                $deleted += $n;
                $n = 0;
            }

            $more = $n > 0 ? ' (time limit reached, continuing next run)' : '';
            $this->line("{$table}: deleted {$deleted} row(s) older than {$days} days{$more}.");
        } catch (\Throwable $e) {
            report($e);
            $this->error("{$table}: prune failed: {$e->getMessage()}");
        }
    }

    private function isHypertable($db, string $table): bool
    {
        try {
            return (bool) $db->selectOne(
                'SELECT 1 AS ok FROM timescaledb_information.hypertables WHERE hypertable_name = ? LIMIT 1',
                [$table]
            );
        } catch (\Throwable $e) {
            return false;   // TimescaleDB not installed in this database
        }
    }
}
