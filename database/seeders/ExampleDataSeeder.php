<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Department;
use App\Models\Request;
use App\Models\ApprovalRule;
use App\Models\AuditLog;
use App\Models\Notification;
use App\Models\Procurement;
use App\Models\ApprovalToken;
use Illuminate\Support\Facades\Hash;

class ExampleDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create Departments
        $departments = [
            ['name' => 'Information Technology', 'description' => 'IT Department'],
            ['name' => 'Human Resources', 'description' => 'HR Department'],
            ['name' => 'Finance', 'description' => 'Finance Department'],
            ['name' => 'Operations', 'description' => 'Operations Department'],
            ['name' => 'Sales', 'description' => 'Sales Department'],
            ['name' => 'Marketing', 'description' => 'Marketing Department'],
            ['name' => 'Procurement', 'description' => 'Procurement Department'],
            ['name' => 'Executive', 'description' => 'Executive Department'],
        ];

        foreach ($departments as $dept) {
            Department::create($dept);
        }

        // Create Users
        $users = [
            // IT Department
            [
                'full_name' => 'John Doe',
                'email' => 'john.doe@company.com',
                'password' => Hash::make('password'),
                'department_id' => 1,
                'role' => 'Employee',
                'permissions' => ['submit_requests', 'view_own_requests']
            ],
            [
                'full_name' => 'Sarah Wilson',
                'email' => 'sarah.wilson@company.com',
                'password' => Hash::make('password'),
                'department_id' => 1,
                'role' => 'Manager',
                'permissions' => ['approve_requests', 'view_department_requests', 'manage_team']
            ],

            // HR Department
            [
                'full_name' => 'Jane Smith',
                'email' => 'jane.smith@company.com',
                'password' => Hash::make('password'),
                'department_id' => 2,
                'role' => 'Employee',
                'permissions' => ['submit_requests', 'view_own_requests']
            ],
            [
                'full_name' => 'Michael Brown',
                'email' => 'michael.brown@company.com',
                'password' => Hash::make('password'),
                'department_id' => 2,
                'role' => 'Manager',
                'permissions' => ['approve_requests', 'view_department_requests', 'manage_team']
            ],

            // Finance Department
            [
                'full_name' => 'Emily Davis',
                'email' => 'emily.davis@company.com',
                'password' => Hash::make('password'),
                'department_id' => 3,
                'role' => 'Employee',
                'permissions' => ['submit_requests', 'view_own_requests']
            ],
            [
                'full_name' => 'Robert Johnson',
                'email' => 'robert.johnson@company.com',
                'password' => Hash::make('password'),
                'department_id' => 3,
                'role' => 'Manager',
                'permissions' => ['approve_requests', 'view_department_requests', 'manage_team']
            ],

            // Sales Department
            [
                'full_name' => 'Mike Johnson',
                'email' => 'mike.johnson@company.com',
                'password' => Hash::make('password'),
                'department_id' => 5,
                'role' => 'SalesManager',
                'permissions' => ['approve_purchase_requests', 'view_all_requests', 'manage_sales']
            ],

            // Procurement Department
            [
                'full_name' => 'Lisa Chen',
                'email' => 'lisa.chen@company.com',
                'password' => Hash::make('password'),
                'department_id' => 7,
                'role' => 'Procurement',
                'permissions' => ['process_orders', 'update_delivery_status', 'manage_procurement']
            ],

            // Executive Department
            [
                'full_name' => 'David Brown',
                'email' => 'david.brown@company.com',
                'password' => Hash::make('password'),
                'department_id' => 8,
                'role' => 'CEO',
                'permissions' => ['approve_high_value_requests', 'view_all_requests', 'system_admin']
            ],

            // Admin
            [
                'full_name' => 'Admin User',
                'email' => 'admin@company.com',
                'password' => Hash::make('password'),
                'department_id' => 1,
                'role' => 'Admin',
                'permissions' => ['full_access']
            ]
        ];

        foreach ($users as $user) {
            User::create($user);
        }

        // Create Approval Rules
        $approvalRules = [
            // IT Department Rules
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
                'order' => 1
            ],

            // HR Department Rules
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
                'order' => 1
            ],

            // Finance Department Rules
            [
                'department_id' => 3,
                'min_amount' => 0,
                'max_amount' => 5000,
                'approver_role' => 'Manager',
                'order' => 1
            ],
            [
                'department_id' => 3,
                'min_amount' => 5000,
                'max_amount' => 50000,
                'approver_role' => 'CEO',
                'order' => 1
            ],
        ];

        foreach ($approvalRules as $rule) {
            ApprovalRule::create($rule);
        }

        // Create Sample Requests
        $requests = [
            [
                'employee_id' => 1, // John Doe
                'item' => 'Dell Latitude 5520 Laptop',
                'description' => 'High-performance laptop for software development work',
                'amount' => 8500.00,
                'status' => 'Approved'
            ],
            [
                'employee_id' => 1, // John Doe
                'item' => 'Office Supplies',
                'description' => 'Stationery and office materials for daily work',
                'amount' => 1500.00,
                'status' => 'Pending'
            ],
            [
                'employee_id' => 3, // Jane Smith
                'item' => 'Conference Room Equipment',
                'description' => 'Projector and audio system for meeting rooms',
                'amount' => 3200.00,
                'status' => 'Rejected'
            ],
            [
                'employee_id' => 5, // Emily Davis
                'item' => 'Accounting Software License',
                'description' => 'Annual license for accounting software',
                'amount' => 2500.00,
                'status' => 'Delivered'
            ],
            [
                'employee_id' => 1, // John Doe
                'item' => 'Standing Desk',
                'description' => 'Adjustable standing desk for ergonomic workspace',
                'amount' => 800.00,
                'status' => 'Approved'
            ],
            [
                'employee_id' => 3, // Jane Smith
                'item' => 'Training Materials',
                'description' => 'Books and materials for employee training program',
                'amount' => 1200.00,
                'status' => 'Pending'
            ],
            [
                'employee_id' => 5, // Emily Davis
                'item' => 'Financial Analysis Software',
                'description' => 'Software for financial data analysis and reporting',
                'amount' => 4500.00,
                'status' => 'Approved'
            ],
            [
                'employee_id' => 1, // John Doe
                'item' => 'Server Hardware',
                'description' => 'Dell PowerEdge server for data center',
                'amount' => 12000.00,
                'status' => 'Pending'
            ]
        ];

        foreach ($requests as $request) {
            Request::create($request);
        }

        // Create Sample Audit Logs
        $auditLogs = [
            [
                'user_id' => 1,
                'request_id' => 1,
                'action' => 'Submitted',
                'notes' => 'Request submitted for approval',
                'ip_address' => '192.168.1.100',
                'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                'created_at' => now()->subDays(5)
            ],
            [
                'user_id' => 2,
                'request_id' => 1,
                'action' => 'Approved',
                'notes' => 'Approved by IT Manager - necessary for development work',
                'ip_address' => '192.168.1.101',
                'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                'created_at' => now()->subDays(4)
            ],
            [
                'user_id' => 7,
                'request_id' => 1,
                'action' => 'Approved',
                'notes' => 'Approved by Sales Manager - purchase-related item',
                'ip_address' => '192.168.1.102',
                'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                'created_at' => now()->subDays(3)
            ],
            [
                'user_id' => 9,
                'request_id' => 1,
                'action' => 'Approved',
                'notes' => 'Approved by CEO - high-value request',
                'ip_address' => '192.168.1.103',
                'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                'created_at' => now()->subDays(2)
            ],
            [
                'user_id' => 1,
                'request_id' => 2,
                'action' => 'Submitted',
                'notes' => 'Request submitted for approval',
                'ip_address' => '192.168.1.100',
                'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                'created_at' => now()->subDays(1)
            ],
            [
                'user_id' => 1,
                'request_id' => 3,
                'action' => 'Submitted',
                'notes' => 'Request submitted for approval',
                'ip_address' => '192.168.1.100',
                'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                'created_at' => now()->subDays(3)
            ],
            [
                'user_id' => 4,
                'request_id' => 3,
                'action' => 'Rejected',
                'notes' => 'Rejected by HR Manager - budget constraints',
                'ip_address' => '192.168.1.104',
                'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                'created_at' => now()->subDays(2)
            ]
        ];

        foreach ($auditLogs as $log) {
            AuditLog::create($log);
        }

        // Create Sample Notifications
        $notifications = [
            [
                'request_id' => 1,
                'receiver_id' => 2,
                'channel' => 'InApp',
                'message' => 'New approval request: Dell Latitude 5520 Laptop',
                'status' => 'Read',
                'created_at' => now()->subDays(5)
            ],
            [
                'request_id' => 1,
                'receiver_id' => 7,
                'channel' => 'InApp',
                'message' => 'New approval request: Dell Latitude 5520 Laptop',
                'status' => 'Read',
                'created_at' => now()->subDays(4)
            ],
            [
                'request_id' => 1,
                'receiver_id' => 9,
                'channel' => 'InApp',
                'message' => 'New approval request: Dell Latitude 5520 Laptop',
                'status' => 'Read',
                'created_at' => now()->subDays(3)
            ],
            [
                'request_id' => 1,
                'receiver_id' => 1,
                'channel' => 'InApp',
                'message' => 'Your request for Dell Latitude 5520 Laptop has been approved',
                'status' => 'Read',
                'created_at' => now()->subDays(2)
            ],
            [
                'request_id' => 2,
                'receiver_id' => 2,
                'channel' => 'InApp',
                'message' => 'New approval request: Office Supplies',
                'status' => 'Unread',
                'created_at' => now()->subDays(1)
            ],
            [
                'request_id' => 3,
                'receiver_id' => 3,
                'channel' => 'InApp',
                'message' => 'Your request for Conference Room Equipment has been rejected',
                'status' => 'Read',
                'created_at' => now()->subDays(2)
            ]
        ];

        foreach ($notifications as $notification) {
            Notification::create($notification);
        }

        // Create Sample Procurement Records
        $procurements = [
            [
                'request_id' => 1,
                'status' => 'Ordered',
                'final_cost' => 8500.00,
                'created_at' => now()->subDays(2)
            ],
            [
                'request_id' => 4,
                'status' => 'Delivered',
                'final_cost' => 2500.00,
                'created_at' => now()->subDays(5)
            ],
            [
                'request_id' => 5,
                'status' => 'Ordered',
                'final_cost' => 800.00,
                'created_at' => now()->subDays(1)
            ],
            [
                'request_id' => 7,
                'status' => 'Ordered',
                'final_cost' => 4500.00,
                'created_at' => now()->subDays(3)
            ]
        ];

        foreach ($procurements as $procurement) {
            Procurement::create($procurement);
        }

        // Create Sample Approval Tokens
        $approvalTokens = [
            [
                'token' => 'sample_token_1',
                'request_id' => 2,
                'approver_id' => 2,
                'action_type' => 'approve',
                'expires_at' => now()->addHours(48),
                'usage_count' => 0,
                'max_usage' => 1,
                'is_used' => false,
                'created_at' => now()->subDays(1)
            ],
            [
                'token' => 'sample_token_2',
                'request_id' => 6,
                'approver_id' => 4,
                'action_type' => 'approve',
                'expires_at' => now()->addHours(24),
                'usage_count' => 0,
                'max_usage' => 1,
                'is_used' => false,
                'created_at' => now()->subHours(12)
            ]
        ];

        foreach ($approvalTokens as $token) {
            ApprovalToken::create($token);
        }

        $this->command->info('Example data seeded successfully!');
        $this->command->info('Created:');
        $this->command->info('- ' . count($departments) . ' Departments');
        $this->command->info('- ' . count($users) . ' Users');
        $this->command->info('- ' . count($approvalRules) . ' Approval Rules');
        $this->command->info('- ' . count($requests) . ' Sample Requests');
        $this->command->info('- ' . count($auditLogs) . ' Audit Log Entries');
        $this->command->info('- ' . count($notifications) . ' Notifications');
        $this->command->info('- ' . count($procurements) . ' Procurement Records');
        $this->command->info('- ' . count($approvalTokens) . ' Approval Tokens');
    }
}
