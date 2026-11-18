<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'aq';

    public function up(): void
    {
        // Fresh installs: the Python services (api_server.py / air_quality_ingest.py)
        // normally create this table on first start, which may not have happened
        // yet. Same schema as theirs, so their CREATE IF NOT EXISTS stays a no-op.
        DB::connection('aq')->statement('
            CREATE TABLE IF NOT EXISTS stations (
                station_mn VARCHAR(32) PRIMARY KEY,
                station_name VARCHAR(100),
                enabled BOOLEAN NOT NULL DEFAULT TRUE,
                latitude DOUBLE PRECISION,
                longitude DOUBLE PRECISION,
                lead_ip VARCHAR(64),
                lead_port INTEGER,
                lead_slave INTEGER,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ');

        // The 'aq' stations table on this server already has this column
        // (added outside migration tracking at some point), so guard
        // against re-adding it — without this, artisan fails here every
        // time and never reaches migrations dated after this one.
        if (! Schema::connection('aq')->hasColumn('stations', 'deleted')) {
            Schema::connection('aq')->table('stations', function (Blueprint $table) {
                $table->boolean('deleted')->default(false);
            });
        }
    }

    public function down(): void
    {
        if (Schema::connection('aq')->hasColumn('stations', 'deleted')) {
            Schema::connection('aq')->table('stations', function (Blueprint $table) {
                $table->dropColumn('deleted');
            });
        }
    }
};