<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::connection('api')->create('api_keys', function (Blueprint $table) {
            $table->string('token_hash')->primary();
            $table->string('owner_label');
            $table->boolean('enabled')->default(true);
            $table->timestamp('created_at')->useCurrent();
            // No updated_at — ApiKey::UPDATED_AT is set to null.
        });
    }

    public function down()
    {
        Schema::connection('api')->dropIfExists('api_keys');
    }
};