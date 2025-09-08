<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Department;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create a default department if it doesn't exist
        $department = Department::firstOrCreate(
            ['name' => 'IT Department'],
            [
                'description' => 'Information Technology Department',
                'budget_limit' => 100000,
                'approval_required' => true
            ]
        );

        // Create admin user
        User::firstOrCreate(
            ['email' => 'admin@company.com'],
            [
                'full_name' => 'System Administrator',
                'password' => Hash::make('password123'),
                'department_id' => $department->id,
                'role' => 'Admin',
                'permissions' => json_encode(['*']) // All permissions
            ]
        );

        // Create a test employee user
        User::firstOrCreate(
            ['email' => 'employee@company.com'],
            [
                'full_name' => 'Test Employee',
                'password' => Hash::make('password123'),
                'department_id' => $department->id,
                'role' => 'Employee',
                'permissions' => json_encode(['submit_requests', 'view_all_requests'])
            ]
        );

        // Create a manager user
        User::firstOrCreate(
            ['email' => 'manager@company.com'],
            [
                'full_name' => 'Department Manager',
                'password' => Hash::make('password123'),
                'department_id' => $department->id,
                'role' => 'Manager',
                'permissions' => json_encode(['submit_requests', 'approve_requests', 'view_all_requests', 'manage_team'])
            ]
        );
    }
}
