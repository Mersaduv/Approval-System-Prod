<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Department;
use App\Models\User;
use App\Models\Role;
use App\Models\ApprovalRule;
use Illuminate\Support\Facades\Hash;

class WorkflowSeeder extends Seeder
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

        // Create departments
        $departments = [
            ['name' => 'IT Department', 'description' => 'Information Technology Department', 'role_id' => $adminRole->id],
            ['name' => 'Finance Department', 'description' => 'Financial Management Department', 'role_id' => $managerRole->id],
            ['name' => 'HR Department', 'description' => 'Human Resources Department', 'role_id' => $managerRole->id],
            ['name' => 'Operations Department', 'description' => 'Operations Management Department', 'role_id' => $employeeRole->id],
        ];

        foreach ($departments as $dept) {
            Department::firstOrCreate(
                ['name' => $dept['name']],
                $dept
            );
        }

        // Create users
        $users = [
            // Admin
            [
                'full_name' => 'System Administrator',
                'email' => 'admin@company.com',
                'password' => Hash::make('password'),
                'department_id' => 1,
                'role_id' => $adminRole->id,
                'permissions' => ['*']
            ],
            // CEO (Admin role)
            [
                'full_name' => 'Chief Executive Officer',
                'email' => 'ceo@company.com',
                'password' => Hash::make('password'),
                'department_id' => 1,
                'role_id' => $adminRole->id,
                'permissions' => ['*']
            ],
            // IT Manager
            [
                'full_name' => 'IT Manager',
                'email' => 'it.manager@company.com',
                'password' => Hash::make('password'),
                'department_id' => 1,
                'role_id' => $managerRole->id,
                'permissions' => ['approve_requests', 'manage_team']
            ],
            // Finance Manager
            [
                'full_name' => 'Finance Manager',
                'email' => 'finance.manager@company.com',
                'password' => Hash::make('password'),
                'department_id' => 2,
                'role_id' => $managerRole->id,
                'permissions' => ['approve_requests', 'manage_team']
            ],
            // Sales Manager
            [
                'full_name' => 'Sales Manager',
                'email' => 'sales.manager@company.com',
                'password' => Hash::make('password'),
                'department_id' => 3,
                'role_id' => $managerRole->id,
                'permissions' => ['approve_requests', 'manage_team']
            ],
            // Procurement
            [
                'full_name' => 'Procurement Officer',
                'email' => 'procurement@company.com',
                'password' => Hash::make('password'),
                'department_id' => 4,
                'role_id' => $employeeRole->id,
                'permissions' => ['submit_requests', 'view_own_requests']
            ],
            // Employees
            [
                'full_name' => 'John Doe',
                'email' => 'john.doe@company.com',
                'password' => Hash::make('password'),
                'department_id' => 1,
                'role_id' => $employeeRole->id,
                'permissions' => ['submit_requests', 'view_own_requests']
            ],
            [
                'full_name' => 'Jane Smith',
                'email' => 'jane.smith@company.com',
                'password' => Hash::make('password'),
                'department_id' => 2,
                'role_id' => $employeeRole->id,
                'permissions' => ['submit_requests', 'view_own_requests']
            ],
        ];

        foreach ($users as $user) {
            User::firstOrCreate(
                ['email' => $user['email']],
                $user
            );
        }

        // Create approval rules
        $rules = [
            // IT Department rules
            [
                'department_id' => 1,
                'min_amount' => 0,
                'max_amount' => 1000,
                'approver_role' => 'Manager',
                'order' => 1
            ],
            [
                'department_id' => 1,
                'min_amount' => 1000,
                'max_amount' => 5000,
                'approver_role' => 'Manager',
                'order' => 1
            ],
            [
                'department_id' => 1,
                'min_amount' => 5000,
                'max_amount' => 10000,
                'approver_role' => 'CEO',
                'order' => 2
            ],
            // Finance Department rules
            [
                'department_id' => 2,
                'min_amount' => 0,
                'max_amount' => 2000,
                'approver_role' => 'Manager',
                'order' => 1
            ],
            [
                'department_id' => 2,
                'min_amount' => 2000,
                'max_amount' => 10000,
                'approver_role' => 'CEO',
                'order' => 2
            ],
        ];

        foreach ($rules as $rule) {
            ApprovalRule::create($rule);
        }
    }
}
