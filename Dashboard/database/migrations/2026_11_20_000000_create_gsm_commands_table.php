<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Commands for the GSM gateway and the water level stations (Restart buttons
 * on Stations › Water Level). The dashboard adds a row; water_level_gsm.py
 * carries it out and fills in sent_at / done_at / result.
 *
 *   station_mn   NULL = the gateway itself, otherwise the station to command
 *   command      'restart'
 *   result       short human-readable status shown on the page
 */
return new class extends Migration
{
    protected $connection = 'water_level';

    public function up(): void
    {
        if (Schema::connection('water_level')->hasTable('gsm_commands')) {
            return;
        }
        Schema::connection('water_level')->create('gsm_commands', function (Blueprint $table) {
            $table->id();
            $table->string('station_mn', 14)->nullable();
            $table->string('command', 20);
            $table->timestampTz('requested_at')->useCurrent();
            $table->timestampTz('sent_at')->nullable();
            $table->timestampTz('done_at')->nullable();
            $table->boolean('failed')->default(false);
            $table->string('result', 200)->nullable();
            $table->index(['sent_at', 'requested_at']);
        });
    }

    public function down(): void
    {
        Schema::connection('water_level')->dropIfExists('gsm_commands');
    }
};
