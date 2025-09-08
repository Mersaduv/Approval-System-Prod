<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Department;
use App\Models\Role;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get roles
        $adminRole = Role::where('name', 'admin')->first();
        $managerRole = Role::where('name', 'manager')->first();
        $employeeRole = Role::where('name', 'employee')->first();

        // Create a default department if it doesn't exist
        $department = Department::firstOrCreate(
            ['name' => 'IT Department'],
            [
                'description' => 'Information Technology Department',
                'role_id' => $adminRole->id
            ]
        );

        // Create admin user
        User::firstOrCreate(
            ['email' => 'admin@company.com'],
            [
                'full_name' => 'System Administrator',
                'password' => Hash::make('password123'),
                'department_id' => $department->id,
                'role_id' => $adminRole->id,
                'permissions' => ['*'] // All permissions
            ]
        );

        // Create a test employee user
        User::firstOrCreate(
            ['email' => 'employee@company.com'],
            [
                'full_name' => 'Test Employee',
                'password' => Hash::make('password123'),
                'department_id' => $department->id,
                'role_id' => $employeeRole->id,
                'permissions' => ['submit_requests', 'view_own_requests']
            ]
        );

        // Create a manager user
        User::firstOrCreate(
            ['email' => 'manager@company.com'],
            [
                'full_name' => 'Department Manager',
                'password' => Hash::make('password123'),
                'department_id' => $department->id,
                'role_id' => $managerRole->id,
                'permissions' => ['submit_requests', 'approve_requests', 'view_all_requests', 'manage_team']
            ]
        );
    }
}
