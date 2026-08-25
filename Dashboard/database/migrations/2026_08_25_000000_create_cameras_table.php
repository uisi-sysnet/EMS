<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cameras', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique(); // mediamtx path name, e.g. "front-gate"
            $table->string('location')->nullable();

            $table->string('ip_address');
            $table->unsignedInteger('onvif_port')->default(80);
            $table->string('username');
            $table->text('password'); // encrypted via the Camera model's cast

            // Populated automatically by syncing with the camera over ONVIF —
            // never entered by hand.
            $table->string('onvif_profile_token')->nullable();
            $table->text('rtsp_uri')->nullable(); // as returned by GetStreamUri, no credentials embedded

            $table->boolean('enabled')->default(true);
            $table->timestamp('last_synced_at')->nullable();
            $table->string('last_status')->nullable(); // online | offline | error
            $table->text('last_error')->nullable();

            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cameras');
    }
};
