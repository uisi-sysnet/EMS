<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * GSM (SMS) reporting for water level stations.
 *
 * Sensors send readings by SMS to the gateway's Nano + SIM800L, which hands
 * them to scripts/water_level_gsm.py over USB serial. The reporting interval
 * is set per station in the dashboard; the service texts it to the sensor and
 * records when the sensor confirms it (see docs in water_level_gsm.py).
 *
 * stations:
 *   sim_number                 the sensor's SIM, used to match incoming SMS
 *                              and to send it settings
 *   report_interval_minutes    interval chosen in the dashboard
 *   applied_interval_minutes   interval the sensor last reported using
 *   interval_sent_at           when the setting was last texted to the sensor
 *   interval_applied_at        when the sensor confirmed it
 *   lead_ip                    now optional: GSM stations have no IP
 *
 * sensor_data: distance (m, raw ultrasonic reading) and seq (sensor message
 * counter, to spot lost SMS).
 *
 * gsm_messages: every SMS the gateway receives, parsed or not, for auditing.
 */
return new class extends Migration
{
    protected $connection = 'water_level';

    public function up(): void
    {
        $schema = Schema::connection('water_level');

        $schema->table('stations', function (Blueprint $table) {
            $table->string('sim_number', 20)->nullable()->unique()->after('lead_slave');
            $table->unsignedSmallInteger('report_interval_minutes')->default(15)->after('sim_number');
            $table->unsignedSmallInteger('applied_interval_minutes')->nullable()->after('report_interval_minutes');
            $table->timestampTz('interval_sent_at')->nullable()->after('applied_interval_minutes');
            $table->timestampTz('interval_applied_at')->nullable()->after('interval_sent_at');
            $table->string('lead_ip', 15)->nullable()->change();
        });

        $schema->table('sensor_data', function (Blueprint $table) {
            $table->double('distance')->nullable()->after('water_level');   // meters
            $table->unsignedInteger('seq')->nullable()->after('temperature');
        });

        if (! $schema->hasTable('gsm_messages')) {
            $schema->create('gsm_messages', function (Blueprint $table) {
                $table->id();
                $table->timestampTz('received_at')->useCurrent();
                $table->string('sender', 32)->nullable();
                $table->string('modem_timestamp', 32)->nullable();
                $table->text('raw_body')->nullable();
                $table->boolean('parsed_ok')->default(false);
                $table->text('parse_error')->nullable();
                $table->string('station_mn', 14)->nullable();

                $table->index('received_at');
            });
        }
    }

    public function down(): void
    {
        $schema = Schema::connection('water_level');

        $schema->dropIfExists('gsm_messages');

        $schema->table('sensor_data', function (Blueprint $table) {
            $table->dropColumn(['distance', 'seq']);
        });

        $schema->table('stations', function (Blueprint $table) {
            $table->dropUnique(['sim_number']);
            $table->dropColumn(['sim_number', 'report_interval_minutes', 'applied_interval_minutes', 'interval_sent_at', 'interval_applied_at']);
        });
    }
};
