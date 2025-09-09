<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Department;
use App\Models\Role;
use App\Models\Request;
use App\Models\ApprovalRule;
use App\Models\AuditLog;
use App\Models\Notification;
use App\Models\Procurement;
use App\Models\ApprovalToken;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

class CleanDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Clear existing data
        $this->command->info('Clearing existing data...');

        // Delete in reverse order to avoid foreign key constraints
        DB::table('approval_tokens')->delete();
        DB::table('procurements')->delete();
        DB::table('notifications')->delete();
        DB::table('audit_logs')->delete();
        DB::table('requests')->delete();
        DB::table('approval_rules')->delete();
        DB::table('users')->delete();
        DB::table('departments')->delete();

        $this->command->info('Existing data cleared.');

        // Get roles
        $adminRole = Role::where('name', Role::ADMIN)->first();
        $managerRole = Role::where('name', Role::MANAGER)->first();
        $employeeRole = Role::where('name', Role::EMPLOYEE)->first();

        // Create Departments
        $departments = [
            [
                'name' => 'NOC',
                'description' => 'Network Operations Center',
                'role_id' => $managerRole->id
            ],
            [
                'name' => 'Sales',
                'description' => 'Sales Department',
                'role_id' => $managerRole->id
            ],
            [
                'name' => 'Finance',
                'description' => 'Finance Department',
                'role_id' => $managerRole->id
            ],
            [
                'name' => 'Administration',
                'description' => 'System Administration',
                'role_id' => $adminRole->id
            ]
        ];

        foreach ($departments as $dept) {
            Department::create($dept);
        }

        // Get department IDs
        $nocDept = Department::where('name', 'NOC')->first();
        $salesDept = Department::where('name', 'Sales')->first();
        $financeDept = Department::where('name', 'Finance')->first();
        $adminDept = Department::where('name', 'Administration')->first();

        // Create Users
        $users = [
            // Admin user
            [
                'full_name' => 'System Administrator',
                'email' => 'admin@company.com',
                'password' => Hash::make('password'),
                'department_id' => $adminDept->id,
                'role_id' => $adminRole->id,
                'permissions' => ['*']
            ],

            // NOC Manager
            [
                'full_name' => 'NOC Manager',
                'email' => 'noc.manager@company.com',
                'password' => Hash::make('password'),
                'department_id' => $nocDept->id,
                'role_id' => $managerRole->id,
                'permissions' => ['approve_requests', 'view_department_requests', 'manage_team', 'view_reports']
            ],

            // NOC Employees
            [
                'full_name' => 'NOC Engineer 1',
                'email' => 'noc.engineer1@company.com',
                'password' => Hash::make('password'),
                'department_id' => $nocDept->id,
                'role_id' => $employeeRole->id,
                'permissions' => ['submit_requests', 'view_own_requests']
            ],
            [
                'full_name' => 'NOC Engineer 2',
                'email' => 'noc.engineer2@company.com',
                'password' => Hash::make('password'),
                'department_id' => $nocDept->id,
                'role_id' => $employeeRole->id,
                'permissions' => ['submit_requests', 'view_own_requests']
            ],
            [
                'full_name' => 'NOC Technician',
                'email' => 'noc.technician@company.com',
                'password' => Hash::make('password'),
                'department_id' => $nocDept->id,
                'role_id' => $employeeRole->id,
                'permissions' => ['submit_requests', 'view_own_requests']
            ],

            // Sales Manager
            [
                'full_name' => 'Sales Manager',
                'email' => 'sales.manager@company.com',
                'password' => Hash::make('password'),
                'department_id' => $salesDept->id,
                'role_id' => $managerRole->id,
                'permissions' => ['approve_requests', 'view_department_requests', 'manage_team', 'view_reports']
            ],

            // Sales Employees
            [
                'full_name' => 'Sales Representative 1',
                'email' => 'sales.rep1@company.com',
                'password' => Hash::make('password'),
                'department_id' => $salesDept->id,
                'role_id' => $employeeRole->id,
                'permissions' => ['submit_requests', 'view_own_requests']
            ],
            [
                'full_name' => 'Sales Representative 2',
                'email' => 'sales.rep2@company.com',
                'password' => Hash::make('password'),
                'department_id' => $salesDept->id,
                'role_id' => $employeeRole->id,
                'permissions' => ['submit_requests', 'view_own_requests']
            ],
            [
                'full_name' => 'Sales Coordinator',
                'email' => 'sales.coordinator@company.com',
                'password' => Hash::make('password'),
                'department_id' => $salesDept->id,
                'role_id' => $employeeRole->id,
                'permissions' => ['submit_requests', 'view_own_requests']
            ],

            // Finance Manager
            [
                'full_name' => 'Finance Manager',
                'email' => 'finance.manager@company.com',
                'password' => Hash::make('password'),
                'department_id' => $financeDept->id,
                'role_id' => $managerRole->id,
                'permissions' => ['approve_requests', 'view_department_requests', 'manage_team', 'view_reports']
            ],

            // Finance Employees
            [
                'full_name' => 'Finance Analyst 1',
                'email' => 'finance.analyst1@company.com',
                'password' => Hash::make('password'),
                'department_id' => $financeDept->id,
                'role_id' => $employeeRole->id,
                'permissions' => ['submit_requests', 'view_own_requests']
            ],
            [
                'full_name' => 'Finance Analyst 2',
                'email' => 'finance.analyst2@company.com',
                'password' => Hash::make('password'),
                'department_id' => $financeDept->id,
                'role_id' => $employeeRole->id,
                'permissions' => ['submit_requests', 'view_own_requests']
            ],
            [
                'full_name' => 'Finance Assistant',
                'email' => 'finance.assistant@company.com',
                'password' => Hash::make('password'),
                'department_id' => $financeDept->id,
                'role_id' => $employeeRole->id,
                'permissions' => ['submit_requests', 'view_own_requests']
            ]
        ];

        foreach ($users as $user) {
            User::create($user);
        }

        // Create Approval Rules
        $approvalRules = [
            // NOC Department Rules
            [
                'department_id' => $nocDept->id,
                'min_amount' => 0,
                'max_amount' => 1000,
                'approver_role' => 'Manager',
                'order' => 1
            ],
            [
                'department_id' => $nocDept->id,
                'min_amount' => 1000,
                'max_amount' => 5000,
                'approver_role' => 'Manager',
                'order' => 1
            ],
            [
                'department_id' => $nocDept->id,
                'min_amount' => 5000,
                'max_amount' => 10000,
                'approver_role' => 'Admin',
                'order' => 1
            ],

            // Sales Department Rules
            [
                'department_id' => $salesDept->id,
                'min_amount' => 0,
                'max_amount' => 2000,
                'approver_role' => 'Manager',
                'order' => 1
            ],
            [
                'department_id' => $salesDept->id,
                'min_amount' => 2000,
                'max_amount' => 10000,
                'approver_role' => 'Manager',
                'order' => 1
            ],
            [
                'department_id' => $salesDept->id,
                'min_amount' => 10000,
                'max_amount' => 50000,
                'approver_role' => 'Admin',
                'order' => 1
            ],

            // Finance Department Rules
            [
                'department_id' => $financeDept->id,
                'min_amount' => 0,
                'max_amount' => 5000,
                'approver_role' => 'Manager',
                'order' => 1
            ],
            [
                'department_id' => $financeDept->id,
                'min_amount' => 5000,
                'max_amount' => 50000,
                'approver_role' => 'Manager',
                'order' => 1
            ],
            [
                'department_id' => $financeDept->id,
                'min_amount' => 50000,
                'max_amount' => 100000,
                'approver_role' => 'Admin',
                'order' => 1
            ]
        ];

        foreach ($approvalRules as $rule) {
            ApprovalRule::create($rule);
        }

        $this->command->info('Clean data seeded successfully!');
        $this->command->info('Created:');
        $this->command->info('- ' . count($departments) . ' Departments');
        $this->command->info('- ' . count($users) . ' Users');
        $this->command->info('- ' . count($approvalRules) . ' Approval Rules');

        $this->command->info('');
        $this->command->info('Login Credentials:');
        $this->command->info('Admin: admin@company.com / password');
        $this->command->info('NOC Manager: noc.manager@company.com / password');
        $this->command->info('Sales Manager: sales.manager@company.com / password');
        $this->command->info('Finance Manager: finance.manager@company.com / password');
    }
}
