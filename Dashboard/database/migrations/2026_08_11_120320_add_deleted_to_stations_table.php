<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'aq';

    public function up(): void
    {
        Schema::table('stations', function (Blueprint $table) {
            $table->boolean('deleted')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('stations', function (Blueprint $table) {
            $table->dropColumn('deleted');
        });
    }
};