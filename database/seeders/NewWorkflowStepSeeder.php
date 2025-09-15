<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\WorkflowStep;
use App\Models\WorkflowStepAssignment;
use App\Models\Role;
use App\Models\User;

class NewWorkflowStepSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Clear existing data
        WorkflowStepAssignment::truncate();
        WorkflowStep::truncate();

        // Get roles
        $adminRole = Role::where('name', 'admin')->first();
        $managerRole = Role::where('name', 'manager')->first();
        $procurementRole = Role::where('name', 'procurement')->first();
        $employeeRole = Role::where('name', 'employee')->first();

        // Get some users for user assignments
        $users = User::take(3)->get();

        // Step 1: Procurement Verification
        $step1 = WorkflowStep::create([
            'name' => 'Procurement Verification',
            'description' => 'تایید اولیه توسط تیم تدارکات',
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

        // Assign procurement role to step 1
        WorkflowStepAssignment::create([
            'workflow_step_id' => $step1->id,
            'assignable_type' => 'App\\Models\\Role',
            'assignable_id' => $procurementRole->id,
            'is_required' => true,
            'priority' => 1
        ]);

        // Step 2: Auto-Approval for small amounts
        $step2 = WorkflowStep::create([
            'name' => 'Auto-Approval',
            'description' => 'تایید خودکار برای مبالغ کم',
            'order_index' => 1,
            'is_active' => true,
            'is_required' => false,
            'step_type' => 'approval',
            'timeout_hours' => 0,
            'auto_approve_if_condition_met' => true,
            'conditions' => [
                ['field' => 'amount', 'operator' => '<=', 'value' => 1000]
            ]
        ]);

        // Step 3: Manager Approval
        $step3 = WorkflowStep::create([
            'name' => 'Manager Approval',
            'description' => 'تایید توسط مدیر بخش',
            'order_index' => 2,
            'is_active' => true,
            'is_required' => true,
            'step_type' => 'approval',
            'timeout_hours' => 48,
            'auto_approve_if_condition_met' => false,
            'conditions' => [
                ['field' => 'amount', 'operator' => '>', 'value' => 1000]
            ]
        ]);

        // Assign manager role to step 3
        WorkflowStepAssignment::create([
            'workflow_step_id' => $step3->id,
            'assignable_type' => 'App\\Models\\Role',
            'assignable_id' => $managerRole->id,
            'is_required' => true,
            'priority' => 1
        ]);

        // Step 4: CEO/Admin Approval
        $step4 = WorkflowStep::create([
            'name' => 'CEO/Admin Approval',
            'description' => 'تایید نهایی توسط مدیرعامل یا ادمین',
            'order_index' => 3,
            'is_active' => true,
            'is_required' => true,
            'step_type' => 'approval',
            'timeout_hours' => 72,
            'auto_approve_if_condition_met' => false,
            'conditions' => [
                ['field' => 'amount', 'operator' => '>', 'value' => 5000]
            ]
        ]);

        // Assign admin role to step 4
        WorkflowStepAssignment::create([
            'workflow_step_id' => $step4->id,
            'assignable_type' => 'App\\Models\\Role',
            'assignable_id' => $adminRole->id,
            'is_required' => true,
            'priority' => 1
        ]);

        // Step 5: Procurement Processing
        $step5 = WorkflowStep::create([
            'name' => 'Procurement Processing',
            'description' => 'پردازش نهایی توسط تیم تدارکات',
            'order_index' => 4,
            'is_active' => true,
            'is_required' => true,
            'step_type' => 'approval',
            'timeout_hours' => 168,
            'auto_approve_if_condition_met' => false,
            'conditions' => [
                ['field' => 'status', 'operator' => '=', 'value' => 'Approved']
            ]
        ]);

        // Assign procurement role to step 5
        WorkflowStepAssignment::create([
            'workflow_step_id' => $step5->id,
            'assignable_type' => 'App\\Models\\Role',
            'assignable_id' => $procurementRole->id,
            'is_required' => true,
            'priority' => 1
        ]);

        // Step 6: User-specific assignment example
        $step6 = WorkflowStep::create([
            'name' => 'Special User Approval',
            'description' => 'تایید توسط کاربر مشخص',
            'order_index' => 5,
            'is_active' => true,
            'is_required' => true,
            'step_type' => 'approval',
            'timeout_hours' => 24,
            'auto_approve_if_condition_met' => false,
            'conditions' => [
                ['field' => 'department_id', 'operator' => '=', 'value' => 1]
            ]
        ]);

        // Assign specific user to step 6 (if users exist)
        if ($users->count() > 0) {
            WorkflowStepAssignment::create([
                'workflow_step_id' => $step6->id,
                'assignable_type' => 'App\\Models\\User',
                'assignable_id' => $users->first()->id,
                'is_required' => true,
                'priority' => 1
            ]);
        }

        $this->command->info('Workflow steps created successfully with new structure!');
    }
}
