<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('water_level')->create('stations', function (Blueprint $table) {
            $table->id();

            // Station identification
            $table->string('station_mn', 14)->unique();
            $table->string('station_name', 32);
            $table->boolean('enabled')->default(true);

            // Location
            $table->string('location')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();

            // Heights (meters)
            $table->decimal('installation_height', 8, 2)->nullable();
            $table->decimal('elevation_height', 8, 2)->nullable();

            // Network / Modbus
            $table->string('lead_ip', 15)->unique();
            $table->unsignedInteger('lead_port')->default(8899);
            $table->unsignedSmallInteger('lead_slave')->default(1);

            $table->timestamps();
            $table->softDeletes();

            $table->index('enabled');
            $table->index('location');
        });
    }

    public function down(): void
    {
        Schema::connection('water_level')->dropIfExists('stations');
    }
};