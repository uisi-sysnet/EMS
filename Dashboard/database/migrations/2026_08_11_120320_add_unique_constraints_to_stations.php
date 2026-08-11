<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('aq')->table('stations', function (Blueprint $table) {
            // Drop existing indexes if any
            $table->dropUnique(['station_mn']); // Only if it exists
            $table->dropUnique(['station_name']); // Only if it exists
            $table->dropUnique(['lead_ip']); // Only if it exists
            
            // Add unique constraints
            $table->unique('station_mn', 'stations_station_mn_unique');
            $table->unique('station_name', 'stations_station_name_unique');
            $table->unique('lead_ip', 'stations_lead_ip_unique');
        });
    }

    public function down(): void
    {
        Schema::connection('aq')->table('stations', function (Blueprint $table) {
            $table->dropUnique('stations_station_mn_unique');
            $table->dropUnique('stations_station_name_unique');
            $table->dropUnique('stations_lead_ip_unique');
        });
    }
};