<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'aq';

    public function up(): void
    {
        // The 'aq' stations table on this server already has this column
        // (added outside migration tracking at some point), so guard
        // against re-adding it — without this, artisan fails here every
        // time and never reaches migrations dated after this one.
        if (! Schema::connection('aq')->hasColumn('stations', 'deleted')) {
            Schema::connection('aq')->table('stations', function (Blueprint $table) {
                $table->boolean('deleted')->default(false);
            });
        }
    }

    public function down(): void
    {
        if (Schema::connection('aq')->hasColumn('stations', 'deleted')) {
            Schema::connection('aq')->table('stations', function (Blueprint $table) {
                $table->dropColumn('deleted');
            });
        }
    }
};