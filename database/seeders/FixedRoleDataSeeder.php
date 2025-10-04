<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Role;
use App\Models\User;
use App\Models\Department;
use Illuminate\Support\Facades\Hash;

class FixedRoleDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get role IDs
        $adminRole = Role::where('name', Role::ADMIN)->first();
        $managerRole = Role::where('name', Role::MANAGER)->first();
        $employeeRole = Role::where('name', Role::EMPLOYEE)->first();
        $procurementRole = Role::where('name', Role::PROCUREMENT)->first();

        // Create departments
        $salesDept = Department::create([
            'name' => 'Sales',
            'description' => 'Sales Department',
            'role_id' => $managerRole->id
        ]);

        $itDept = Department::create([
            'name' => 'IT',
            'description' => 'Information Technology Department',
            'role_id' => $managerRole->id
        ]);

        $hrDept = Department::create([
            'name' => 'HR',
            'description' => 'Human Resources Department',
            'role_id' => $managerRole->id
        ]);

        // Create admin department
        $adminDept = Department::create([
            'name' => 'Administration',
            'description' => 'System Administration',
            'role_id' => $adminRole->id
        ]);

        // Create procurement department
        $procurementDept = Department::create([
            'name' => 'Procurement',
            'description' => 'Procurement and Purchasing Department',
            'role_id' => $procurementRole->id
        ]);

        // Create admin user
        User::create([
            'full_name' => 'System Administrator',
            'email' => 'admin@company.com',
            'password' => Hash::make('password'),
            'department_id' => $adminDept->id,
            'role_id' => $adminRole->id,
        ]);

        // Create sales manager
        User::create([
            'full_name' => 'John Smith',
            'email' => 'john.smith@company.com',
            'password' => Hash::make('password'),
            'department_id' => $salesDept->id,
            'role_id' => $managerRole->id,
        ]);

        // Create IT manager
        User::create([
            'full_name' => 'Jane Doe',
            'email' => 'jane.doe@company.com',
            'password' => Hash::make('password'),
            'department_id' => $itDept->id,
            'role_id' => $managerRole->id,
        ]);

        // Create HR manager
        User::create([
            'full_name' => 'Mike Johnson',
            'email' => 'mike.johnson@company.com',
            'password' => Hash::make('password'),
            'department_id' => $hrDept->id,
            'role_id' => $managerRole->id,
        ]);

        // Create sales employees
        User::create([
            'full_name' => 'Alice Brown',
            'email' => 'alice.brown@company.com',
            'password' => Hash::make('password'),
            'department_id' => $salesDept->id,
            'role_id' => $employeeRole->id,
        ]);

        User::create([
            'full_name' => 'Bob Wilson',
            'email' => 'bob.wilson@company.com',
            'password' => Hash::make('password'),
            'department_id' => $salesDept->id,
            'role_id' => $employeeRole->id,
        ]);

        // Create IT employees
        User::create([
            'full_name' => 'Charlie Davis',
            'email' => 'charlie.davis@company.com',
            'password' => Hash::make('password'),
            'department_id' => $itDept->id,
            'role_id' => $employeeRole->id,
        ]);

        User::create([
            'full_name' => 'Diana Lee',
            'email' => 'diana.lee@company.com',
            'password' => Hash::make('password'),
            'department_id' => $itDept->id,
            'role_id' => $employeeRole->id,
        ]);

        // Create HR employees
        User::create([
            'full_name' => 'Eva Martinez',
            'email' => 'eva.martinez@company.com',
            'password' => Hash::make('password'),
            'department_id' => $hrDept->id,
            'role_id' => $employeeRole->id,
        ]);

        // Create procurement users
        User::create([
            'full_name' => 'Sarah Wilson',
            'email' => 'sarah.wilson@company.com',
            'password' => Hash::make('password'),
            'department_id' => $procurementDept->id,
            'role_id' => $procurementRole->id,
        ]);

        User::create([
            'full_name' => 'Tom Anderson',
            'email' => 'tom.anderson@company.com',
            'password' => Hash::make('password'),
            'department_id' => $procurementDept->id,
            'role_id' => $procurementRole->id,
        ]);

        $this->command->info('Fixed role system data seeded successfully!');
        $this->command->info('Admin: admin@company.com / password');
        $this->command->info('Sales Manager: john.smith@company.com / password');
        $this->command->info('IT Manager: jane.doe@company.com / password');
        $this->command->info('HR Manager: mike.johnson@company.com / password');
        $this->command->info('Procurement: sarah.wilson@company.com / password');
        $this->command->info('Procurement: tom.anderson@company.com / password');
    }
}
