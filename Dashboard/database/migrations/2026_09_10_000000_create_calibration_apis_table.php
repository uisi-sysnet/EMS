<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'aq';

    public function up(): void
    {
        Schema::create('calibration_apis', function (Blueprint $table) {
            $table->id();
            
            // Source name (e.g., AccuStation, OpenWeather, AccuWeather, etc.)
            $table->string('source')->index();
            
            // The full API endpoint URL — TEXT to allow long URLs
            $table->text('api_url');
            
            // Bearer token (encrypted) — TEXT because encrypted values are ~300-500 chars
            $table->text('api_token')->nullable();
            
            // Authentication type
            $table->string('auth_type')->default('bearer_token');
            
            // File path
            $table->string('file_path')->nullable();
            
            // Checklist of data fields (JSON array)
            $table->json('checklist')->nullable();
            
            // Total number of data records
            $table->unsignedInteger('total_data')->default(0);
            
            // Number of requests per minute
            $table->unsignedSmallInteger('requests_per_min')->default(0);
            
            // Timestamps
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calibration_apis');
    }
};