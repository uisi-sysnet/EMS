<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The database connection that should be used by the migration.
     *
     * @var string
     */
    protected $connection = 'aq';

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('calibration_apis', function (Blueprint $table) {
            $table->id();
            
            // Source name (e.g., AccuStation, OpenWeather, AccuWeather, etc.)
            $table->string('source')->index();
            
            // The full API endpoint URL
            $table->string('api_url');
            
            // Bearer token (encrypted in production)
            $table->string('api_token')->nullable();
            
            // Authentication type – defaults to 'api_key'
            $table->string('auth_type')->default('api_key');
            
            // File path (if a local file is associated, e.g., uploaded calibration file)
            $table->string('file_path')->nullable();
            
            // Checklist of data fields (JSON array)
            $table->json('checklist')->nullable();
            
            // Total number of data records
            $table->unsignedInteger('total_data')->default(0);
            
            // Number of requests per minute
            $table->unsignedSmallInteger('requests_per_min')->default(0);
            
            // Timestamps
            $table->timestamps();
            
            // Optional: add soft deletes if needed
            // $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('calibration_apis');
    }
};