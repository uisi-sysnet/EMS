<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Readings for water level stations (IOT_water_level), one row per
 * measurement. Columns follow the sensor_data design from the original
 * water level work (WaterLevelSensorData: water_level, battery_voltage,
 * temperature, recorded_at).
 *
 * The dashboard and CityWatch treat a station as online when it has a
 * recent row here, the same rule as air quality and seismic stations.
 */
return new class extends Migration
{
    protected $connection = 'water_level';

    public function up(): void
    {
        if (Schema::connection('water_level')->hasTable('sensor_data')) {
            return;
        }

        Schema::connection('water_level')->create('sensor_data', function (Blueprint $table) {
            $table->id();
            $table->string('station_mn', 14);
            $table->double('water_level')->nullable();       // meters
            $table->double('battery_voltage')->nullable();   // volts
            $table->double('temperature')->nullable();       // °C
            $table->timestampTz('recorded_at');
            $table->timestampTz('created_at')->useCurrent();

            $table->index(['station_mn', 'recorded_at']);
        });
    }

    public function down(): void
    {
        Schema::connection('water_level')->dropIfExists('sensor_data');
    }
};
