<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('telegram_settings', function (Blueprint $table) {
            $table->id();

            // Single-row settings table — the app always reads/writes the
            // first (and only) row via TelegramSetting::current(), never
            // ::first() directly. bot_token is encrypted at rest via the
            // model's cast, so it's stored as ciphertext (needs a `text`
            // column, not `string`).
            $table->text('bot_token')->nullable();
            $table->string('chat_id')->nullable();

            $table->boolean('daily_digest_enabled')->default(false);
            // Plain "HH:MM" string (not a time column) — compared directly
            // against now('Asia/Manila')->format('H:i') in the scheduled
            // command, and pairs 1:1 with an HTML <input type="time">.
            $table->string('daily_digest_time', 5)->default('08:00');
            // Guards against sending twice if schedule:run overlaps within
            // the same minute; cleared implicitly each new day.
            $table->date('last_digest_sent_date')->nullable();

            $table->boolean('offline_alert_enabled')->default(false);
            $table->boolean('health_alert_enabled')->default(false);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('telegram_settings');
    }
};