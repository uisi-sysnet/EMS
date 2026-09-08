<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('aq')->table('stations', function (Blueprint $table) {
            // Try to drop using the known index name
            $table->dropUnique('stations_lead_ip_unique');
        });
    }

    public function down(): void
    {
        Schema::connection('aq')->table('stations', function (Blueprint $table) {
            // Re-add if you ever rollback
            $table->unique('lead_ip', 'stations_lead_ip_unique');
        });
    }
};