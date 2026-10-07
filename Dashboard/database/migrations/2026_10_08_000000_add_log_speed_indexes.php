<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Indexes so the log pages and the menu badge don't scan whole log tables:
 *
 * - service_logs: a small partial index of the unread entries that need
 *   attention (SystemLog::needsAttention()), which the menu badge, the
 *   notification bell and the Logs page count on every page load; and
 *   created_at for sorting and the date range.
 * - api_request_logs: created_at, for sorting and the date range.
 *
 * Run `php artisan logs:prune` first on a gateway with a huge log table, so
 * these are built over far fewer rows. Plain tables are indexed
 * CONCURRENTLY (no write lock); TimescaleDB hypertables don't support that,
 * so they're indexed one chunk at a time instead.
 */
return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        $this->index('logs', 'service_logs', 'idx_service_logs_attention_unseen',
            '(created_at DESC)',
            "seen_at IS NULL AND (level IN ('ERROR', 'CRITICAL') OR (category = 'device' AND level = 'WARNING'))",
            ['seen_at', 'category']);
        $this->index('logs', 'service_logs', 'idx_service_logs_created_at', '(created_at DESC)');
        $this->index('api', 'api_request_logs', 'idx_api_request_logs_created_at', '(created_at DESC)');
    }

    public function down(): void
    {
        DB::connection('logs')->statement('DROP INDEX IF EXISTS idx_service_logs_attention_unseen');
        DB::connection('logs')->statement('DROP INDEX IF EXISTS idx_service_logs_created_at');
        DB::connection('api')->statement('DROP INDEX IF EXISTS idx_api_request_logs_created_at');
    }

    private function index(string $connection, string $table, string $name, string $columns, ?string $where = null, array $needsColumns = []): void
    {
        try {
            $db = DB::connection($connection);
            $schema = $db->getSchemaBuilder();
            if (!$schema->hasTable($table)) {
                return;
            }
            foreach ($needsColumns as $column) {
                if (!$schema->hasColumn($table, $column)) {
                    return;
                }
            }

            $hypertable = false;
            try {
                $hypertable = (bool) $db->selectOne(
                    'SELECT 1 AS ok FROM timescaledb_information.hypertables WHERE hypertable_name = ? LIMIT 1', [$table]);
            } catch (\Throwable $e) {
                // TimescaleDB not installed in this database
            }

            // WITH (...) must come before WHERE.
            $where = $where ? " WHERE {$where}" : '';
            $db->statement($hypertable
                ? "CREATE INDEX IF NOT EXISTS {$name} ON {$table} {$columns} WITH (timescaledb.transaction_per_chunk){$where}"
                : "CREATE INDEX CONCURRENTLY IF NOT EXISTS {$name} ON {$table} {$columns}{$where}");
        } catch (\Throwable $e) {
            // An index only speeds things up; never fail the deploy over it.
            report($e);
        }
    }
};
