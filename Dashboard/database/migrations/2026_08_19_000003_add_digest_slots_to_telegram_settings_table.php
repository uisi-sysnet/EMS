<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('telegram_settings', function (Blueprint $table) {
            $table->boolean('morning_digest_enabled')->default(false)->after('chat_id');
            $table->string('morning_digest_time', 5)->default('08:00')->after('morning_digest_enabled');
            $table->date('morning_digest_last_sent_date')->nullable()->after('morning_digest_time');

            $table->boolean('afternoon_digest_enabled')->default(false)->after('morning_digest_last_sent_date');
            $table->string('afternoon_digest_time', 5)->default('17:00')->after('afternoon_digest_enabled');
            $table->date('afternoon_digest_last_sent_date')->nullable()->after('afternoon_digest_time');
        });

        // Carry forward whatever was already configured under the old
        // single-slot columns into the new "morning" slot, so upgrading
        // doesn't silently turn off notifications for anyone already
        // using this.
        DB::table('telegram_settings')->update([
            'morning_digest_enabled'        => DB::raw('daily_digest_enabled'),
            'morning_digest_time'           => DB::raw('daily_digest_time'),
            'morning_digest_last_sent_date' => DB::raw('last_digest_sent_date'),
        ]);

        Schema::table('telegram_settings', function (Blueprint $table) {
            $table->dropColumn(['daily_digest_enabled', 'daily_digest_time', 'last_digest_sent_date']);
        });
    }

    public function down(): void
    {
        Schema::table('telegram_settings', function (Blueprint $table) {
            $table->boolean('daily_digest_enabled')->default(false);
            $table->string('daily_digest_time', 5)->default('08:00');
            $table->date('last_digest_sent_date')->nullable();
        });

        DB::table('telegram_settings')->update([
            'daily_digest_enabled'  => DB::raw('morning_digest_enabled'),
            'daily_digest_time'     => DB::raw('morning_digest_time'),
            'last_digest_sent_date' => DB::raw('morning_digest_last_sent_date'),
        ]);

        Schema::table('telegram_settings', function (Blueprint $table) {
            $table->dropColumn([
                'morning_digest_enabled', 'morning_digest_time', 'morning_digest_last_sent_date',
                'afternoon_digest_enabled', 'afternoon_digest_time', 'afternoon_digest_last_sent_date',
            ]);
        });
    }
};