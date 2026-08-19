<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'api';

    public function up(): void
    {
        // Table already exists on this server (created outside migration
        // tracking at some point) — skip creation to avoid the duplicate
        // table error and let this migration record as run.
        if (! Schema::connection('api')->hasTable('api_request_logs')) {
            Schema::connection('api')->create('api_request_logs', function (Blueprint $table) {
                $table->string('client_ip')->nullable();
                $table->string('method', 10);
                $table->string('path');
                $table->unsignedSmallInteger('status_code');
                $table->float('duration_ms')->nullable();
                $table->string('api_key_owner')->nullable();
                $table->string('api_key_used')->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->timestamp('seen_at')->nullable();

                // No primary key / no id column
            });
        }
    }

    public function down(): void
    {
        Schema::connection('api')->dropIfExists('api_request_logs');
    }
};