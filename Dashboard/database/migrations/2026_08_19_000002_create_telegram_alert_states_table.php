<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('telegram_alert_states', function (Blueprint $table) {
            $table->id();

            // e.g. 'station:Air Quality:14', 'health:cpu'. Deliberately a
            // generic string key rather than a foreign key to stations —
            // it needs to track health metrics too, and this way it never
            // needs a migration change if station tables change.
            $table->string('key')->unique();

            // Station keys: 'online' | 'idle' | 'offline'.
            // Health keys:  'good' | 'warning' | 'critical'.
            $table->string('last_status');
            $table->timestamp('last_notified_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('telegram_alert_states');
    }
};