<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{

    protected $connection = 'aq';

    public function up(): void
    {
        Schema::table('calibration_apis', function (Blueprint $table) {
            $table->decimal('requests_per_min', 10, 4)->default(0)->change();
        });
    }

    public function down(): void
    {
        Schema::table('calibration_apis', function (Blueprint $table) {
            $table->smallInteger('requests_per_min')->default(0)->change();
        });
    }
};