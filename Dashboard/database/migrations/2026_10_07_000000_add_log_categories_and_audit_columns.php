<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Logs page (two tabs):
 *
 * - Logs: service_logs (IOT_service_logs) gets a category — system, device
 *   or security. The Python services tag device/security messages
 *   (scripts/db_logging.py adds this column itself too, whichever runs
 *   first); the dashboard's device status tracker writes device events.
 *
 * - Audit Log: the existing, previously unused activity_log table records
 *   who did what in the dashboard (App\Http\Middleware\AuditTrail). It
 *   gains columns for the person, their role and IP, and whether the
 *   action succeeded, so the tab can filter on them directly.
 *
 * - device_status_states: last known status per device, so
 *   logs:track-device-status logs only changes (online <-> offline).
 */
return new class extends Migration
{
    public function up(): void
    {
        $logs = Schema::connection('logs');
        if ($logs->hasTable('service_logs') && ! $logs->hasColumn('service_logs', 'category')) {
            $logs->table('service_logs', function (Blueprint $table) {
                $table->string('category', 16)->default('system');
                $table->index(['category', 'created_at']);
            });
        }

        Schema::table('activity_log', function (Blueprint $table) {
            $table->string('username', 64)->nullable()->after('causer_id');
            $table->string('role', 32)->nullable()->after('username');
            $table->string('ip_address', 45)->nullable()->after('role');
            $table->string('user_agent', 255)->nullable()->after('ip_address');
            $table->string('result', 16)->default('success')->after('event');
            $table->index('created_at');
            $table->index('username');
        });

        Schema::create('device_status_states', function (Blueprint $table) {
            $table->id();
            $table->string('key', 120)->unique();     // e.g. "aq:STN001", "camera:cam-1"
            $table->string('status', 16);            // up | down
            $table->timestampTz('changed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_status_states');

        Schema::table('activity_log', function (Blueprint $table) {
            $table->dropIndex(['created_at']);
            $table->dropIndex(['username']);
            $table->dropColumn(['username', 'role', 'ip_address', 'user_agent', 'result']);
        });

        $logs = Schema::connection('logs');
        if ($logs->hasTable('service_logs') && $logs->hasColumn('service_logs', 'category')) {
            $logs->table('service_logs', function (Blueprint $table) {
                $table->dropIndex(['category', 'created_at']);
                $table->dropColumn('category');
            });
        }
    }
};
