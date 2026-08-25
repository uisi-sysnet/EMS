<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('cameras', function (Blueprint $table) {
            $table->id();
            
            $table->string('channel', 50);
            $table->string('name');
            $table->string('slug')->unique()->comment('MediaMTX path name, e.g. "front-gate"');
            $table->string('location')->nullable();
            
            // ONVIF connection details
            $table->string('ip_address');
            $table->unsignedInteger('onvif_port')->default(80);
            $table->string('username');
            $table->text('password')->comment('Encrypted via the Camera model\'s cast');
            
            // ONVIF discovery data - populated automatically, never entered manually
            $table->string('onvif_profile_token')->nullable();
            $table->text('rtsp_uri')->nullable()->comment('As returned by GetStreamUri, no credentials embedded');
            
            // Device metadata
            $table->string('device_type', 50)->nullable();
            $table->string('serial_number', 50)->unique()->nullable();
            
            // Geolocation
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            
            // Status and monitoring
            $table->boolean('enabled')->default(true);
            $table->timestamp('last_synced_at')->nullable();
            $table->string('last_status')->nullable()->comment('online | offline | error');
            $table->text('last_error')->nullable();
            
            $table->text('notes')->nullable();
            $table->timestamps();
            
            // Soft delete
            $table->boolean('deleted_at')->default(false);
            
            // ===== INDEXES =====
            
            // Primary lookup indexes
            $table->index('slug'); // Already unique, but explicit index for joins
            $table->index('serial_number'); // Already unique, but explicit index for joins
            
            // Status filtering
            $table->index(['enabled', 'last_status']);
            $table->index(['enabled', 'last_synced_at']);
            
            // Network/connection lookups
            $table->index('ip_address');
            $table->index('onvif_port');
            
            // Location-based queries
            $table->index('location');
            
            // Geographic queries (spatial indexing)
            $table->index(['latitude', 'longitude']);
            
            // Device type filtering
            $table->index('device_type');
            
            // Composite indexes for common query patterns
            $table->index(['enabled', 'device_type', 'last_status']);
            $table->index(['location', 'enabled']);
            
            // Full-text search (if using MySQL, otherwise comment out)
            // $table->fullText(['name', 'location', 'notes']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cameras');
    }
};