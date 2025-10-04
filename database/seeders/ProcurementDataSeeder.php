<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Role;
use App\Models\User;
use App\Models\Department;
use Illuminate\Support\Facades\Hash;

class ProcurementDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get procurement role
        $procurementRole = Role::where('name', Role::PROCUREMENT)->first();

        if (!$procurementRole) {
            $this->command->error('Procurement role not found. Please run the migration first.');
            return;
        }

        // Check if procurement department already exists
        $procurementDept = Department::where('name', 'Procurement')->first();

        if (!$procurementDept) {
            // Create procurement department
            $procurementDept = Department::create([
                'name' => 'Procurement',
                'description' => 'Procurement and Purchasing Department',
                'role_id' => $procurementRole->id
            ]);

            $this->command->info('Procurement department created successfully!');
        } else {
            $this->command->info('Procurement department already exists.');
        }

        // Create procurement users if they don't exist
        $procurementUsers = [
            [
                'full_name' => 'Sarah Wilson',
                'email' => 'sarah.wilson@company.com',
            ],
            [
                'full_name' => 'Tom Anderson',
                'email' => 'tom.anderson@company.com',
            ]
        ];

        foreach ($procurementUsers as $userData) {
            $existingUser = User::where('email', $userData['email'])->first();

            if (!$existingUser) {
                User::create([
                    'full_name' => $userData['full_name'],
                    'email' => $userData['email'],
                    'password' => Hash::make('password'),
                    'department_id' => $procurementDept->id,
                    'role_id' => $procurementRole->id,
                ]);

                $this->command->info("Created procurement user: {$userData['email']}");
            } else {
                $this->command->info("User {$userData['email']} already exists.");
            }
        }

        $this->command->info('Procurement data seeded successfully!');
        $this->command->info('Procurement users:');
        $this->command->info('- sarah.wilson@company.com / password');
        $this->command->info('- tom.anderson@company.com / password');
    }
}
