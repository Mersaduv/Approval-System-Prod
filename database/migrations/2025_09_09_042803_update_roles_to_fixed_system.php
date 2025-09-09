<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Clear existing roles and create fixed roles
        DB::table('roles')->truncate();

        // Insert fixed roles
        DB::table('roles')->insert([
            [
                'name' => 'admin',
                'description' => 'Full system access with all permissions',
                'permissions' => json_encode(['*']),
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'manager',
                'description' => 'Department manager with approval and team management permissions',
                'permissions' => json_encode([
                    'submit_requests',
                    'approve_requests',
                    'view_all_requests',
                    'manage_team',
                    'view_reports',
                    'view_audit_logs'
                ]),
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'employee',
                'description' => 'Basic employee with request submission permissions',
                'permissions' => json_encode([
                    'submit_requests',
                    'view_own_requests'
                ]),
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        ]);

        // Update existing users to have proper role assignments
        // First, get the role IDs
        $adminRoleId = DB::table('roles')->where('name', 'admin')->value('id');
        $managerRoleId = DB::table('roles')->where('name', 'manager')->value('id');
        $employeeRoleId = DB::table('roles')->where('name', 'employee')->value('id');

        // Update users based on their current role or assign default employee role
        DB::table('users')->update([
            'role_id' => $employeeRoleId // Default all users to employee role
        ]);

        // Update departments to have manager role assigned
        DB::table('departments')->update([
            'role_id' => $managerRoleId
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // This migration is not reversible as it changes the fundamental role structure
        // If you need to rollback, you would need to restore from backup
    }
};
