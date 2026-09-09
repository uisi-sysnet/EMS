<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('api_calibration', function (Blueprint $table) {
            // Primary identifier
            $table->string('station_mn', 32);
            $table->string('ip_address', 45)->nullable(); // supports IPv6
            $table->timestamp('data_time');

            // Air quality measurements
            $table->float('pm25')->nullable();
            $table->float('pm10')->nullable();
            $table->float('tsp')->nullable();
            $table->float('ozone')->nullable();
            $table->float('carbon_monoxide')->nullable();
            $table->float('sulfur_dioxide')->nullable();
            $table->float('nitrogen_dioxide')->nullable();

            // Meteorological data
            $table->float('temperature')->nullable();
            $table->float('humidity')->nullable();
            $table->float('rain')->nullable();
            $table->float('wind_speed')->nullable();
            $table->float('wind_direction')->nullable();
            $table->float('air_pressure')->nullable();

            // Other measurements
            $table->float('noise')->nullable();
            $table->float('lead')->nullable();
            $table->float('lead_temperature')->nullable();

            // Timestamp
            $table->timestamp('created_at')->nullable();

            // Foreign key to stations table (assuming 'stations' exists)
            $table->foreign('station_mn')
                  ->references('station_mn')
                  ->on('stations')
                  ->onDelete('cascade');

            // Optional: composite unique key to avoid duplicate entries for same station and time
            $table->unique(['station_mn', 'data_time'], 'api_calibration_station_time_unique');

            // Indexes for common query filters
            $table->index('data_time');
            $table->index('ip_address');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('api_calibration');
    }
};