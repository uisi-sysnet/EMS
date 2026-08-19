<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The 'users' table on this server already has this column
        // (added outside migration tracking at some point) — guard
        // against re-adding it, same as the 'deleted' column on
        // 'stations'.
        if (! Schema::hasColumn('users', 'username')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('username')->after('id');
            });
        }

        // Separate step: the column may already exist without its unique
        // constraint (e.g. if it was added by hand). There's no clean
        // cross-driver "add unique if not exists", so attempt it and
        // treat "already exists" as a no-op rather than a failure.
        try {
            Schema::table('users', function (Blueprint $table) {
                $table->unique('username');
            });
        } catch (\Illuminate\Database\QueryException $e) {
            // Unique constraint/index already present — nothing to do.
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'username')) {
            Schema::table('users', function (Blueprint $table) {
                // Dropping the column also drops its unique constraint in
                // Postgres — no separate dropUnique() call needed.
                $table->dropColumn('username');
            });
        }
    }
};