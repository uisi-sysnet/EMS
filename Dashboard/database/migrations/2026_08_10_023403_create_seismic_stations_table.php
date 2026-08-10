<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * This table lives on the app's own default connection (same as
     * `stations`) — it is NOT on the seismic Postgres database. It's the
     * local registry of "which seismic stations have we registered",
     * separate from `station_metrics` on the seismic connection, which
     * only holds actual sensor readings sent in by hardware.
     */
    public function up(): void
    {
        Schema::create('seismic_stations', function (Blueprint $table) {
            // Matches station_metrics.station_id (varchar(50)) on the
            // seismic connection, so the two can be joined on this value.
            $table->string('station_id', 50)->primary();
            $table->string('station_name', 100)->nullable();
            $table->boolean('enabled')->default(true);
            $table->double('latitude')->nullable();
            $table->double('longitude')->nullable();
            $table->float('elevation_m')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seismic_stations');
    }
};