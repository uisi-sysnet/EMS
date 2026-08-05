<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('api_keys', function (Blueprint $table) {
            // Rename columns
            $table->renameColumn('name', 'owner_label');
            $table->renameColumn('key', 'token_hash');

            // Add new boolean column with default true (enabled by default)
            $table->boolean('enabled')->default(true)->after('token_hash');

            // If you don't need updated_at, you can drop it:
            // $table->dropTimestamps(); // but careful: this removes both created_at and updated_at
            // To keep only created_at:
            // $table->dropColumn('updated_at');

            // But the request says "add enabled boolean and created at instead" – 
            // created_at already exists, so no action needed.
        });
    }

    public function down()
    {
        Schema::table('api_keys', function (Blueprint $table) {
            $table->renameColumn('owner_label', 'name');
            $table->renameColumn('token_hash', 'key');
            $table->dropColumn('enabled');
            // If you dropped updated_at, re-add it here:
            // $table->timestamp('updated_at')->nullable();
        });
    }
};