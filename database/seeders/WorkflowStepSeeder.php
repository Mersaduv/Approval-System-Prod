<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\WorkflowStep;
use App\Models\WorkflowStepAssignment;
use App\Models\Role;
use App\Models\Department;

class WorkflowStepSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Clear existing data
        WorkflowStepAssignment::truncate();
        WorkflowStep::truncate();

        // Get roles and departments
        $adminRole = Role::where('name', 'admin')->first();
        $managerRole = Role::where('name', 'manager')->first();
        $procurementRole = Role::where('name', 'procurement')->first();
        $procurementDept = Department::where('name', 'Procurement')->first();

        // Step 1: Procurement Verification
        $step1 = WorkflowStep::create([
            'name' => 'Procurement Verification',
            'description' => 'تایید و بررسی قیمت توسط تیم خرید',
            'order_index' => 0,
            'is_active' => true,
            'is_required' => true,
            'step_type' => 'verification',
            'timeout_hours' => 24,
            'auto_approve_if_condition_met' => false,
            'conditions' => [
                ['field' => 'amount', 'operator' => '>', 'value' => 0]
            ]
        ]);

        // Assign procurement team to step 1
        if ($procurementRole) {
            WorkflowStepAssignment::create([
                'workflow_step_id' => $step1->id,
                'assignable_type' => 'App\\Models\\Role',
                'assignable_id' => $procurementRole->id,
                'is_required' => true,
                'priority' => 1
            ]);
        }

        // Step 2: Manager Approval (for amounts > 1000)
        $step2 = WorkflowStep::create([
            'name' => 'Manager Approval',
            'description' => 'تایید توسط مدیر دپارتمان',
            'order_index' => 1,
            'is_active' => true,
            'is_required' => true,
            'step_type' => 'approval',
            'timeout_hours' => 48,
            'auto_approve_if_condition_met' => false,
            'conditions' => [
                ['field' => 'amount', 'operator' => '>', 'value' => 1000]
            ]
        ]);

        // Assign manager role to step 2
        if ($managerRole) {
            WorkflowStepAssignment::create([
                'workflow_step_id' => $step2->id,
                'assignable_type' => 'App\\Models\\Role',
                'assignable_id' => $managerRole->id,
                'is_required' => true,
                'priority' => 1,
                'conditions' => [
                    ['field' => 'employee.department_id', 'operator' => '=', 'value' => 'assignable.department_id']
                ]
            ]);
        }

        // Step 3: CEO/Admin Approval (for amounts > 5000)
        $step3 = WorkflowStep::create([
            'name' => 'CEO/Admin Approval',
            'description' => 'تایید توسط مدیرعامل یا ادمین',
            'order_index' => 2,
            'is_active' => true,
            'is_required' => true,
            'step_type' => 'approval',
            'timeout_hours' => 72,
            'auto_approve_if_condition_met' => false,
            'conditions' => [
                ['field' => 'amount', 'operator' => '>', 'value' => 5000]
            ]
        ]);

        // Assign admin role to step 3
        if ($adminRole) {
            WorkflowStepAssignment::create([
                'workflow_step_id' => $step3->id,
                'assignable_type' => 'App\\Models\\Role',
                'assignable_id' => $adminRole->id,
                'is_required' => true,
                'priority' => 1
            ]);
        }

        // Step 4: Procurement Processing
        $step4 = WorkflowStep::create([
            'name' => 'Procurement Processing',
            'description' => 'پردازش و سفارش توسط تیم خرید',
            'order_index' => 3,
            'is_active' => true,
            'is_required' => true,
            'step_type' => 'approval',
            'timeout_hours' => 168, // 7 days
            'auto_approve_if_condition_met' => false,
            'conditions' => [
                ['field' => 'status', 'operator' => '=', 'value' => 'Approved']
            ]
        ]);

        // Assign procurement team to step 4
        if ($procurementRole) {
            WorkflowStepAssignment::create([
                'workflow_step_id' => $step4->id,
                'assignable_type' => 'App\\Models\\Role',
                'assignable_id' => $procurementRole->id,
                'is_required' => true,
                'priority' => 1
            ]);
        }

        // Step 5: Auto-approval for small amounts
        $step5 = WorkflowStep::create([
            'name' => 'Auto-Approval',
            'description' => 'تایید خودکار برای مبالغ کم',
            'order_index' => 0,
            'is_active' => true,
            'is_required' => false,
            'step_type' => 'approval',
            'timeout_hours' => 0,
            'auto_approve_if_condition_met' => true,
            'conditions' => [
                ['field' => 'amount', 'operator' => '<=', 'value' => 1000]
            ]
        ]);

        $this->command->info('Workflow steps seeded successfully!');
    }
}
