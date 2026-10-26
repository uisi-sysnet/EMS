<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SMS test for water level stations (Stations › Water Level › Test SMS).
 *
 * The dashboard sets sms_test_requested_at; scripts/water_level_gsm.py texts
 * "WLPING" to the station through the gateway and sets sms_test_sent_at; the
 * station answers "WLPONG,..." and the service sets sms_test_replied_at.
 * sms_test_result is a short human-readable status of the latest test.
 */
return new class extends Migration
{
    protected $connection = 'water_level';

    public function up(): void
    {
        Schema::connection('water_level')->table('stations', function (Blueprint $table) {
            $table->timestampTz('sms_test_requested_at')->nullable();
            $table->timestampTz('sms_test_sent_at')->nullable();
            $table->timestampTz('sms_test_replied_at')->nullable();
            $table->string('sms_test_result', 200)->nullable();
        });
    }

    public function down(): void
    {
        Schema::connection('water_level')->table('stations', function (Blueprint $table) {
            $table->dropColumn(['sms_test_requested_at', 'sms_test_sent_at', 'sms_test_replied_at', 'sms_test_result']);
        });
    }
};
