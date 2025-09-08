<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Role;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $roles = [
            [
                'name' => 'admin',
                'description' => 'Full system access with all permissions',
                'permissions' => ['*'], // All permissions
                'is_active' => true
            ],
            [
                'name' => 'manager',
                'description' => 'Department manager with approval and team management permissions',
                'permissions' => [
                    'submit_requests',
                    'approve_requests',
                    'view_all_requests',
                    'manage_team',
                    'view_reports',
                    'view_audit_logs'
                ],
                'is_active' => true
            ],
            [
                'name' => 'employee',
                'description' => 'Basic employee with request submission permissions',
                'permissions' => [
                    'submit_requests',
                    'view_own_requests'
                ],
                'is_active' => true
            ]
        ];

        foreach ($roles as $role) {
            Role::firstOrCreate(
                ['name' => $role['name']],
                $role
            );
        }
    }
}
