<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Single-row settings for the Uplink Sentinel link (Settings > Sentinel).
 * The token is stored encrypted (SentinelSetting casts it). The last_*
 * columns show the result of the latest send on the settings page.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('sentinel_settings')) {
            return;
        }

        Schema::create('sentinel_settings', function (Blueprint $table) {
            $table->id();
            $table->boolean('enabled')->default(false);
            $table->string('host')->nullable();              // Sentinel IP address or hostname
            $table->unsignedInteger('port')->default(8090);
            $table->boolean('use_https')->default(false);
            $table->text('token')->nullable();               // encrypted
            $table->unsignedInteger('interval_minutes')->default(30);
            $table->string('system_name', 100)->default('EMS-AQ');
            $table->timestamp('last_attempt_at')->nullable();
            $table->timestamp('last_success_at')->nullable();
            $table->unsignedSmallInteger('last_status_code')->nullable();
            $table->text('last_result')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sentinel_settings');
    }
};
