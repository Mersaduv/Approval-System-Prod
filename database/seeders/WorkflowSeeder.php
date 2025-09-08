<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Department;
use App\Models\User;
use App\Models\ApprovalRule;
use Illuminate\Support\Facades\Hash;

class WorkflowSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create departments
        $departments = [
            ['name' => 'IT Department', 'description' => 'Information Technology Department'],
            ['name' => 'Finance Department', 'description' => 'Financial Management Department'],
            ['name' => 'HR Department', 'description' => 'Human Resources Department'],
            ['name' => 'Operations Department', 'description' => 'Operations Management Department'],
        ];

        foreach ($departments as $dept) {
            Department::create($dept);
        }

        // Create users
        $users = [
            // Admin
            [
                'full_name' => 'System Administrator',
                'email' => 'admin@company.com',
                'password' => Hash::make('password'),
                'department_id' => 1,
                'role' => 'Admin',
                'permissions' => json_encode(['*'])
            ],
            // CEO
            [
                'full_name' => 'Chief Executive Officer',
                'email' => 'ceo@company.com',
                'password' => Hash::make('password'),
                'department_id' => 1,
                'role' => 'CEO',
                'permissions' => json_encode(['approve_high_value', 'view_all_requests'])
            ],
            // IT Manager
            [
                'full_name' => 'IT Manager',
                'email' => 'it.manager@company.com',
                'password' => Hash::make('password'),
                'department_id' => 1,
                'role' => 'Manager',
                'permissions' => json_encode(['approve_requests', 'manage_team'])
            ],
            // Finance Manager
            [
                'full_name' => 'Finance Manager',
                'email' => 'finance.manager@company.com',
                'password' => Hash::make('password'),
                'department_id' => 2,
                'role' => 'Manager',
                'permissions' => json_encode(['approve_requests', 'manage_team'])
            ],
            // Sales Manager
            [
                'full_name' => 'Sales Manager',
                'email' => 'sales.manager@company.com',
                'password' => Hash::make('password'),
                'department_id' => 3,
                'role' => 'SalesManager',
                'permissions' => json_encode(['approve_purchases', 'manage_sales'])
            ],
            // Procurement
            [
                'full_name' => 'Procurement Officer',
                'email' => 'procurement@company.com',
                'password' => Hash::make('password'),
                'department_id' => 4,
                'role' => 'Procurement',
                'permissions' => json_encode(['manage_procurement', 'update_status'])
            ],
            // Employees
            [
                'full_name' => 'John Doe',
                'email' => 'john.doe@company.com',
                'password' => Hash::make('password'),
                'department_id' => 1,
                'role' => 'Employee',
                'permissions' => json_encode(['submit_requests'])
            ],
            [
                'full_name' => 'Jane Smith',
                'email' => 'jane.smith@company.com',
                'password' => Hash::make('password'),
                'department_id' => 2,
                'role' => 'Employee',
                'permissions' => json_encode(['submit_requests'])
            ],
        ];

        foreach ($users as $user) {
            User::create($user);
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
