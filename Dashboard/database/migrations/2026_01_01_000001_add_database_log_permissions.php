<?php
// database/migrations/2026_01_01_000001_add_database_log_permissions.php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    public function up()
    {
        // Create permission
        $permission = Permission::firstOrCreate(['name' => 'view database logs']);

        // Assign to admin roles
        $adminRole = Role::where('name', 'admin')->first();
        $superAdminRole = Role::where('name', 'superAdmin')->first();

        if ($adminRole) {
            $adminRole->givePermissionTo($permission);
        }

        if ($superAdminRole) {
            $superAdminRole->givePermissionTo($permission);
        }
    }

    public function down()
    {
        Permission::where('name', 'view database logs')->delete();
    }
};