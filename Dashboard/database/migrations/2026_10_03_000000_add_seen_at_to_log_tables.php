<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The Dashboard marks log entries as seen (SystemLog / ApiLog `seen_at`),
 * but neither log table was ever given that column by code: service_logs is
 * created by scripts/db_logging.py and api_request_logs by
 * scripts/api_server.py (or the 2026_08_11 migration, whichever runs first).
 * Older gateways had it added by hand; fresh installs failed with
 * "column seen_at does not exist".
 *
 * Tables that don't exist yet are skipped here — the Python services add
 * the column themselves when they create the tables.
 */
return new class extends Migration
{
    private array $tables = [
        'logs' => 'service_logs',
        'api'  => 'api_request_logs',
    ];

    public function up(): void
    {
        foreach ($this->tables as $connection => $table) {
            $schema = Schema::connection($connection);
            if ($schema->hasTable($table) && !$schema->hasColumn($table, 'seen_at')) {
                $schema->table($table, function (Blueprint $t) {
                    $t->timestampTz('seen_at')->nullable();
                });
            }
        }
    }

    public function down(): void
    {
        // seen_at predates this migration on existing gateways, so it is
        // intentionally not dropped.
    }
};
