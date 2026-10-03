<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The API IP allowlist is created by scripts/api_server.py on startup, but
 * the Dashboard's API settings page (ApiKeyController) reads and writes it
 * too — so until the API service had run once, that page failed with
 * "relation allowed_ips does not exist". Same definition as api_server.py;
 * IF NOT EXISTS keeps both sides compatible whichever runs first.
 */
return new class extends Migration
{
    protected $connection = 'api';

    public function up(): void
    {
        DB::connection('api')->statement('
            CREATE TABLE IF NOT EXISTS allowed_ips (
                cidr VARCHAR(43) PRIMARY KEY,
                label VARCHAR(100) NOT NULL,
                enabled BOOLEAN NOT NULL DEFAULT TRUE,
                created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
            )
        ');
    }

    public function down(): void
    {
        // Owned by api_server.py as well; dropping it would wipe the live allowlist.
    }
};
