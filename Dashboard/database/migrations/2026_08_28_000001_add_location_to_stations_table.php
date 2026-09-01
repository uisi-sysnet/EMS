<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'aq';

    public function up(): void
    {
        // Guard against re-adding the column if it already exists
        if (! Schema::connection('aq')->hasColumn('stations', 'location')) {
            Schema::connection('aq')->table('stations', function (Blueprint $table) {
                $table->string('location', 255)->nullable()->after('longitude');
            });
        }
    }

    public function down(): void
    {
        if (Schema::connection('aq')->hasColumn('stations', 'location')) {
            Schema::connection('aq')->table('stations', function (Blueprint $table) {
                $table->dropColumn('location');
            });
        }
    }
};