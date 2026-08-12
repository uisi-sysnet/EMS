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
        Schema::table('users', function (Blueprint $table) {
            // Add new columns
            $table->string('first_name')->after('id');
            $table->string('last_name')->after('first_name');
            $table->string('contact_number', 11)->after('last_name');
            $table->string('username')->unique()->after('email');
            $table->string('role')->default('user')->after('password');
            
            // Optional: Rename existing 'name' column to avoid confusion
            // $table->renameColumn('name', 'full_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'first_name',
                'last_name',
                'contact_number',
                'username',
                'role'
            ]);
            
            // Optional: Rename back if you renamed it
            // $table->renameColumn('full_name', 'name');
        });
    }
};