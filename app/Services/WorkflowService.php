<?php

namespace App\Services;

use App\Models\Request as RequestModel;
use App\Models\User;
use App\Models\Role;
use App\Models\ApprovalRule;
use App\Models\WorkflowStep;
use App\Models\WorkflowStepAssignment;
use App\Models\Notification;
use App\Models\AuditLog;
use App\Models\Procurement;
use App\Models\ApprovalToken;
use App\Models\SystemSetting;
use App\Mail\ApprovalNotificationMail;
use App\Mail\EmployeeNotificationMail;
use App\Services\NotificationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class WorkflowService
{
    protected $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     * Submit a new request
     */
    public function submitRequest(array $requestData, int $employeeId): RequestModel
    {
        return DB::transaction(function () use ($requestData, $employeeId) {
            // Create the request
            $request = RequestModel::create([
                'employee_id' => $employeeId,
                'item' => $requestData['item'],
                'description' => $requestData['description'],
                'amount' => $requestData['amount'],
                'status' => 'Pending',
                'procurement_status' => 'Pending Verification'
            ]);

            // Log the submission
            $this->logAction($employeeId, $request->id, 'Submitted', 'Request submitted for procurement verification');

            // Start the procurement verification workflow
            $this->processProcurementVerificationWorkflow($request);

            return $request;
        });
    }

    /**
     * Process procurement verification workflow
     */
    public function processProcurementVerificationWorkflow(RequestModel $request): void
    {
        // Update request status to show it's waiting for procurement verification
        $request->update(['status' => 'Pending Procurement Verification']);

        // Send notification to procurement team for verification
        $this->notificationService->sendProcurementVerificationRequest($request);

        // Log the action
        $this->logAction(1, $request->id, 'Sent for Procurement Verification', 'Request sent to procurement team for market verification and pricing');
    }

    /**
     * Process procurement verification
     */
    public function processProcurementVerification(int $requestId, int $procurementUserId, string $status, float $finalPrice = null, string $notes = null): bool
    {
        return DB::transaction(function () use ($requestId, $procurementUserId, $status, $finalPrice, $notes) {
            $request = RequestModel::findOrFail($requestId);
            $procurementUser = User::findOrFail($procurementUserId);

            // Check if user has procurement role
            if (!$procurementUser->isProcurement()) {
                throw new \Exception('Only procurement team members can verify requests');
            }

            // Check if request is in correct status for verification
            if ($request->procurement_status !== 'Pending Verification') {
                throw new \Exception('Request is not in a valid status for procurement verification');
            }

            // Update procurement verification status
            $request->update([
                'procurement_status' => $status,
                'final_price' => $finalPrice,
                'procurement_notes' => $notes,
                'verified_by' => $procurementUserId,
                'verified_at' => now()
            ]);

            // Log the verification action
            $this->logAction($procurementUserId, $requestId, 'Procurement Verified', "Status: {$status}" . ($notes ? " - {$notes}" : ""));

            // If verified, proceed to approval workflow
            if ($status === 'Verified') {
                // Update the request amount with final price if provided
                if ($finalPrice && $finalPrice > 0) {
                    $request->update(['amount' => $finalPrice]);
                }

                // Start the approval workflow
                $this->processApprovalWorkflow($request);

                // Notify employee that request is verified and sent for approval
                $this->notificationService->sendEmployeeNotification($request, 'procurement_verified');
            } elseif ($status === 'Not Available' || $status === 'Rejected') {
                // Update request status to rejected
                $request->update(['status' => 'Rejected']);

                // Notify employee that request was rejected by procurement
                $this->notificationService->sendEmployeeNotification($request, 'procurement_rejected', $notes);
            }

            return true;
        });
    }

    /**
     * Process the approval workflow based on dynamic workflow steps
     */
    public function processApprovalWorkflow(RequestModel $request): void
    {
        // Get applicable workflow steps for this request
        $steps = WorkflowStep::getStepsForRequest($request);

        if ($steps->isEmpty()) {
            // Fallback to legacy system if no dynamic steps are configured
            $this->processLegacyWorkflow($request);
            return;
        }

        // Process the first applicable step
        $this->processNextWorkflowStep($request, $steps);
    }

    /**
     * Process next workflow step
     */
    private function processNextWorkflowStep(RequestModel $request, $steps): void
    {
        $processed = false;

        foreach ($steps as $step) {
            if ($this->shouldProcessStep($request, $step)) {
                $this->executeWorkflowStep($request, $step);
                $processed = true;
                break; // Only process one step at a time
            }
        }

        // If no step was processed, check if all approvals are complete
        if (!$processed && $this->allApprovalsComplete($request)) {
            $request->update(['status' => 'Approved']);
            $this->logAction(1, $request->id, 'All Approvals Complete', 'All required approvals have been completed');
        }
    }

    /**
     * Check if a step should be processed
     */
    private function shouldProcessStep(RequestModel $request, WorkflowStep $step): bool
    {
        // Check if step is active
        if (!$step->is_active) {
            return false;
        }

        // Check if step conditions are met
        if (!WorkflowStep::shouldExecuteStep($step, $request)) {
            return false;
        }

        // Check if step has already been completed
        if ($this->isStepCompleted($request, $step)) {
            return false;
        }

        return true;
    }

    /**
     * Execute a workflow step
     */
    private function executeWorkflowStep(RequestModel $request, WorkflowStep $step): void
    {
        switch ($step->step_type) {
            case 'approval':
                $this->executeApprovalStep($request, $step);
                break;
            case 'verification':
                $this->executeVerificationStep($request, $step);
                break;
            case 'notification':
                $this->executeNotificationStep($request, $step);
                break;
        }
    }

    /**
     * Execute approval step
     */
    private function executeApprovalStep(RequestModel $request, WorkflowStep $step): void
    {
        // Check for auto-approval
        if ($step->auto_approve_if_condition_met) {
            $this->autoApproveRequest($request);
            return;
        }

        // Get assigned approvers
        $approvers = $step->getAssignedUsers();

        if ($approvers->isEmpty()) {
            Log::warning("No approvers assigned to workflow step: {$step->name}");
            return;
        }

        // Send approval request to the first approver
        $approver = $approvers->first();
        $this->sendApprovalRequest($request, $approver, "Approval required: {$step->name}");

        // Update request status to indicate it's waiting for this specific step
        $request->update(['status' => 'Pending Approval']);
    }

    /**
     * Execute verification step
     */
    private function executeVerificationStep(RequestModel $request, WorkflowStep $step): void
    {
        // This is typically handled by the procurement verification system
        // Just update the request status
        $request->update(['status' => 'Pending Procurement Verification']);

        // Log the action
        $this->logAction(1, $request->id, 'Verification Required', "Verification step: {$step->name}");
    }

    /**
     * Execute notification step
     */
    private function executeNotificationStep(RequestModel $request, WorkflowStep $step): void
    {
        // Send notifications to assigned users
        $users = $step->getAssignedUsers();

        foreach ($users as $user) {
            $this->notificationService->sendEmployeeNotification($request, 'notification', null, $user);
        }

        // Mark step as completed and move to next
        $this->markStepCompleted($request, $step);
        $this->processNextWorkflowStep($request, WorkflowStep::getStepsForRequest($request));
    }

    /**
     * Get current step for approver
     */
    private function getCurrentStepForApprover(RequestModel $request, User $approver): ?WorkflowStep
    {
        $steps = WorkflowStep::getStepsForRequest($request);

        // Find the first incomplete step for this approver
        foreach ($steps as $step) {
            if ($this->isStepForUser($step, $approver) && !$this->isStepCompleted($request, $step)) {
                return $step;
            }
        }

        return null;
    }

    /**
     * Get approver ID from assignment
     */
    private function getApproverIdFromAssignment(WorkflowStepAssignment $assignment): ?int
    {
        switch ($assignment->assignable_type) {
            case 'App\\Models\\User':
                return $assignment->assignable_id;
            case 'App\\Models\\Role':
                // Get first user with this role
                $user = User::where('role_id', $assignment->assignable_id)->first();
                return $user ? $user->id : null;
            case 'App\\Models\\Department':
                // Get first user from this department
                $user = User::where('department_id', $assignment->assignable_id)->first();
                return $user ? $user->id : null;
            default:
                return null;
        }
    }

    /**
     * Check if a step is completed
     */
    private function isStepCompleted(RequestModel $request, WorkflowStep $step): bool
    {
        // Check for step completion in audit logs - this is the most reliable method
        $stepCompleted = AuditLog::where('request_id', $request->id)
            ->where('action', 'Step completed')
            ->where('notes', 'like', '%' . $step->name . '%')
            ->where('notes', 'like', '%Step ID: ' . $step->id . '%')
            ->exists();

        if ($stepCompleted) {
            return true;
        }

        // For approval steps, check if there's a specific approval for this step
        if ($step->step_type === 'approval') {
            // Check if there's an approval with this specific step name and ID
            $stepApproved = AuditLog::where('request_id', $request->id)
                ->where('action', 'Approved')
                ->where('notes', 'like', '%Step: ' . $step->name . '%')
                ->where('notes', 'like', '%Step ID: ' . $step->id . '%')
                ->exists();

            if ($stepApproved) {
                return true;
            }
        }

        // For verification steps, check if procurement verification is completed
        if ($step->step_type === 'verification') {
            return $request->procurement_status === 'Verified';
        }

        return false;
    }

    /**
     * Mark step as completed
     */
    private function markStepCompleted(RequestModel $request, WorkflowStep $step): void
    {
        $this->logAction(1, $request->id, 'Step completed', "Step completed: {$step->name}");
    }


    /**
     * Mark current step as completed based on approver
     */
    private function markCurrentStepCompleted(RequestModel $request, int $approverId, $currentStep = null): void
    {
        $approver = User::findOrFail($approverId);

        // Use the provided step or get the current step that was actually approved
        if (!$currentStep) {
            $currentStep = $this->getCurrentStepForApprover($request, $approver);
        }

        if ($currentStep) {
            // Mark this specific step as completed with unique identifier
            $this->logAction($approverId, $request->id, 'Step completed', "Step completed: {$currentStep->name} by {$approver->full_name} (Step ID: {$currentStep->id})");
        }
    }

    /**
     * Check if step is assigned to user
     */
    private function isStepForUser(WorkflowStep $step, User $user): bool
    {
        $assignments = $step->assignments;

        foreach ($assignments as $assignment) {
            if ($this->isUserAssignedToStep($assignment, $user)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if user is assigned to a specific step assignment
     */
    private function isUserAssignedToStep($assignment, User $user): bool
    {
        switch ($assignment->assignable_type) {
            case 'App\\Models\\User':
                return $assignment->assignable_id == $user->id;
            case 'App\\Models\\Role':
                return $assignment->assignable_id == $user->role_id;
            case 'App\\Models\\Department':
                return $assignment->assignable_id == $user->department_id;
            default:
                return false;
        }
    }

    /**
     * Mark current step as rejected based on rejector
     */
    private function markCurrentStepRejected(RequestModel $request, int $rejectorId): void
    {
        $rejector = User::findOrFail($rejectorId);
        $steps = WorkflowStep::getStepsForRequest($request);

        foreach ($steps as $step) {
            if ($this->isStepForUser($step, $rejector) && !$this->isStepCompleted($request, $step)) {
                $this->logAction($rejectorId, $request->id, 'Step Rejected', "Step rejected: {$step->name}");
                break; // Only mark one step as rejected per rejection
            }
        }
    }

    /**
     * Get action name for step
     */
    private function getStepActionName(WorkflowStep $step): string
    {
        switch ($step->step_type) {
            case 'approval':
                return 'Approved';
            case 'verification':
                return 'Verified';
            case 'notification':
                return 'Notified';
            default:
                return 'Processed';
        }
    }

    /**
     * Fallback to legacy workflow system
     */
    private function processLegacyWorkflow(RequestModel $request): void
    {
        $employee = $request->employee;
        $department = $employee->department;
        $amount = $request->amount;

        // Check if this is an employee request - they go directly to CEO/Admin after procurement verification
        if ($employee->isEmployee()) {
            $this->processEmployeeWorkflow($request);
            return;
        }

        // For other roles (Manager, Procurement, Admin), use normal workflow
        // Get approval rules for this department
        $rules = ApprovalRule::where('department_id', $department->id)
            ->where('min_amount', '<=', $amount)
            ->where('max_amount', '>=', $amount)
            ->orderBy('order')
            ->get();

        if ($rules->isEmpty()) {
            // No specific rules, use default workflow
            $this->processDefaultWorkflow($request);
        } else {
            // Process based on dynamic rules
            $this->processDynamicWorkflow($request, $rules);
        }
    }

    /**
     * Process workflow for employee requests - goes directly to CEO/Admin after procurement verification
     */
    private function processEmployeeWorkflow(RequestModel $request): void
    {
        $amount = $request->amount;

        // Get thresholds from settings
        $autoApprovalThreshold = SystemSetting::get('auto_approval_threshold', 1000);

        // Auto-approval: amount <= auto_approval_threshold
        if ($amount <= $autoApprovalThreshold) {
            // Auto-approve the request
            $this->autoApproveRequest($request);
            return;
        }

        // For amounts above threshold, send directly to CEO/Admin
        $admin = $this->getAdmin();
        if ($admin) {
            $this->sendApprovalRequest($request, $admin, 'CEO/Admin approval required for employee request');
        }
    }

    /**
     * Process default workflow
     */
    private function processDefaultWorkflow(RequestModel $request): void
    {
        $employee = $request->employee;
        $amount = $request->amount;

        // Get thresholds from settings
        $autoApprovalThreshold = SystemSetting::get('auto_approval_threshold', 1000);
        $managerOnlyThreshold = SystemSetting::get('manager_only_threshold', 2000);
        $ceoThreshold = SystemSetting::get('ceo_approval_threshold', 5000);

        // Auto-approval: amount <= auto_approval_threshold
        if ($amount <= $autoApprovalThreshold) {
            // Auto-approve the request
            $this->autoApproveRequest($request);
            return;
        }

        // Manager-only approval: auto_approval_threshold < amount <= manager_only_threshold
        if ($amount <= $managerOnlyThreshold) {
            $manager = $this->getDepartmentManager($employee->department_id);
            if ($manager) {
                $this->sendApprovalRequest($request, $manager, 'Manager approval required');
            } else {
                // If no manager in department, send to admin
                $admin = $this->getAdmin();
                if ($admin) {
                    $this->sendApprovalRequest($request, $admin, 'Admin approval required (no manager in department)');
                }
            }
            return;
        }

        // Manager + CEO approval: amount > manager_only_threshold
        // Step 1: Manager approval (sequential - only send to manager first)
        $manager = $this->getDepartmentManager($employee->department_id);
        if ($manager) {
            $this->sendApprovalRequest($request, $manager, 'Manager approval required');
        } else {
            // If no manager in department, send to admin
            $admin = $this->getAdmin();
            if ($admin) {
                $this->sendApprovalRequest($request, $admin, 'Admin approval required (no manager in department)');
            }
        }
    }

    /**
     * Process dynamic workflow based on rules
     */
    private function processDynamicWorkflow(RequestModel $request, $rules): void
    {
        // For sequential approval, only send to the first approver in the chain
        $firstRule = $rules->first();
        if ($firstRule) {
            $approver = $this->getApproverByRole($firstRule->approver_role, $request->employee->department_id);
            if ($approver) {
                $this->sendApprovalRequest($request, $approver, "Approval required from {$firstRule->approver_role}");
            }
        }
    }


    /**
     * Approve a request
     */
    public function approveRequest(int $requestId, int $approverId, string $notes = null): bool
    {
        return DB::transaction(function () use ($requestId, $approverId, $notes) {
            $request = RequestModel::findOrFail($requestId);
            $approver = User::findOrFail($approverId);

            // Check if this approver can approve this request
            if (!$this->canApprove($request, $approver)) {
                throw new \Exception('You are not authorized to approve this request');
            }

            // Get the current step for this approver
            $currentStep = $this->getCurrentStepForApprover($request, $approver);
            $stepName = $currentStep ? $currentStep->name : 'Unknown Step';
            $stepId = $currentStep ? $currentStep->id : 'Unknown';

            // Log the approval with step information
            $this->logAction($approverId, $requestId, 'Approved', $notes ? "{$notes} (Step: {$stepName}, Step ID: {$stepId})" : "Approved (Step: {$stepName}, Step ID: {$stepId})");

            // Mark current step as completed
            $this->markCurrentStepCompleted($request, $approverId, $currentStep);

            // Check if all required approvals are complete
            if ($this->allApprovalsComplete($request)) {
                // Forward to Procurement
                $this->forwardToProcurement($request, $approverId);

                // Notify employee that request is approved
                $this->notificationService->sendEmployeeNotification($request, 'approved');
            } else {
                // Continue with next approver in the sequential chain
                $this->processNextApproval($request);
            }

            return true;
        });
    }

    /**
     * Reject a request
     */
    public function rejectRequest(int $requestId, int $rejectorId, string $reason): bool
    {
        return DB::transaction(function () use ($requestId, $rejectorId, $reason) {
            $request = RequestModel::findOrFail($requestId);
            $rejector = User::findOrFail($rejectorId);

            // Update request status
            $request->update(['status' => 'Rejected']);

            // Log the rejection
            $this->logAction($rejectorId, $requestId, 'Rejected', $reason);

            // Mark current step as rejected
            $this->markCurrentStepRejected($request, $rejectorId);

            // Notify the employee
            $this->notificationService->sendEmployeeNotification($request, 'rejected', $reason);

            return true;
        });
    }

    /**
     * Forward request to Procurement
     */
    private function forwardToProcurement(RequestModel $request, int $approverId = null): void
    {
        // Create procurement record
        Procurement::create([
            'request_id' => $request->id,
            'status' => 'Pending Procurement'
        ]);

        // Update request status to show it's waiting for procurement
        $request->update(['status' => 'Pending Procurement']);

        // Notify procurement team
        $this->notificationService->sendProcurementNotification($request);

        // Log the action
        $this->logAction($approverId ?? 1, $request->id, 'Forwarded to Procurement', 'Request approved and forwarded to procurement team');
    }

    /**
     * Process procurement approval
     */
    public function processProcurementApproval(int $requestId, int $procurementUserId, string $status, float $finalCost = null, string $notes = null): bool
    {
        return DB::transaction(function () use ($requestId, $procurementUserId, $status, $finalCost, $notes) {
            $request = RequestModel::findOrFail($requestId);
            $procurementUser = User::findOrFail($procurementUserId);

            // Check if user has procurement role
            if (!$procurementUser->isProcurement()) {
                throw new \Exception('Only procurement team members can process procurement approvals');
            }

            // Check if request is in correct status for procurement processing
            if (!in_array($request->status, ['Approved', 'Pending Procurement', 'Ordered'])) {
                throw new \Exception('Request is not in a valid status for procurement processing');
            }

            $procurement = $request->procurement;
            if (!$procurement) {
                // Create procurement record if it doesn't exist
                $initialStatus = $request->status === 'Approved' ? 'Pending Procurement' : 'Ordered';
                $procurement = Procurement::create([
                    'request_id' => $request->id,
                    'status' => $initialStatus
                ]);
            }

            // Update procurement status
            $procurement->update([
                'status' => $status,
                'final_cost' => $finalCost,
                'procurement_user_id' => $procurementUserId
            ]);

            // Update request status based on procurement decision
            if ($status === 'Pending Procurement') {
                $request->update(['status' => 'Pending Procurement']);
                $this->notificationService->sendEmployeeNotification($request, 'procurement_started');
            } elseif ($status === 'Ordered') {
                $request->update(['status' => 'Ordered']);
                $this->notificationService->sendEmployeeNotification($request, 'ordered');
            } elseif ($status === 'Delivered') {
                $request->update(['status' => 'Delivered']);
                $this->notificationService->sendEmployeeNotification($request, 'delivered');
            } elseif ($status === 'Cancelled') {
                $request->update(['status' => 'Cancelled']);
                $this->notificationService->sendEmployeeNotification($request, 'cancelled', $notes);
            }

            // Log the action
            $this->logAction($procurementUserId, $requestId, 'Procurement Processed', "Status: {$status}" . ($notes ? " - {$notes}" : ""));

            return true;
        });
    }

    /**
     * Rollback a cancelled request to pending procurement
     */
    public function rollbackCancelledRequest(int $requestId, int $procurementUserId, string $notes = null): bool
    {
        return DB::transaction(function () use ($requestId, $procurementUserId, $notes) {
            $request = RequestModel::findOrFail($requestId);
            $procurementUser = User::findOrFail($procurementUserId);

            // Check if user has procurement role
            if (!$procurementUser->isProcurement()) {
                throw new \Exception('Only procurement team members can rollback requests');
            }

            // Check if request is cancelled
            if ($request->status !== 'Cancelled') {
                throw new \Exception('Only cancelled requests can be rolled back');
            }

            // Update request status to Pending Procurement
            $request->update(['status' => 'Pending Procurement']);

            // Update or create procurement record
            $procurement = $request->procurement;
            if ($procurement) {
                $procurement->update([
                    'status' => 'Pending Procurement',
                    'procurement_user_id' => $procurementUserId
                ]);
            } else {
                Procurement::create([
                    'request_id' => $request->id,
                    'status' => 'Pending Procurement',
                    'procurement_user_id' => $procurementUserId
                ]);
            }

            // Notify employee that request has been restored
            $this->notificationService->sendEmployeeNotification($request, 'restored', $notes);

            // Log the rollback action
            $this->logAction($procurementUserId, $requestId, 'Request Restored', "Request rolled back from cancelled status" . ($notes ? " - {$notes}" : ""));

            return true;
        });
    }

    /**
     * Update procurement status (legacy method for backward compatibility)
     */
    public function updateProcurementStatus(int $requestId, string $status, float $finalCost = null): bool
    {
        return DB::transaction(function () use ($requestId, $status, $finalCost) {
            $request = RequestModel::findOrFail($requestId);
            $procurement = $request->procurement;

            if (!$procurement) {
                throw new \Exception('No procurement record found for this request');
            }

            // Update procurement status
            $procurement->update([
                'status' => $status,
                'final_cost' => $finalCost
            ]);

            // Update request status if delivered
            if ($status === 'Delivered') {
                $request->update(['status' => 'Delivered']);

            // Notify employee of delivery
            $this->notificationService->sendEmployeeNotification($request, 'delivered');
            }

            // Log the action
            $this->logAction(1, $requestId, 'Procurement Updated', "Status updated to: {$status}");

            return true;
        });
    }

    /**
     * Check if all required approvals are complete
     */
    private function allApprovalsComplete(RequestModel $request): bool
    {
        // Get applicable workflow steps for this request
        $steps = WorkflowStep::getStepsForRequest($request);

        if ($steps->isEmpty()) {
            // Fallback to legacy system
            return $this->allLegacyApprovalsComplete($request);
        }

        // Check if all required steps are completed
        foreach ($steps as $step) {
            if ($step->is_required && !$this->isStepCompleted($request, $step)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Legacy approval completion check
     */
    private function allLegacyApprovalsComplete(RequestModel $request): bool
    {
        $employee = $request->employee;
        $amount = $request->amount;
        $department = $employee->department;

        // Get thresholds from settings
        $autoApprovalThreshold = SystemSetting::get('auto_approval_threshold', 1000);

        // Auto-approval: no additional approvals needed
        if ($amount <= $autoApprovalThreshold) {
            return true;
        }

        // Special handling for employee requests - only need CEO/Admin approval
        if ($employee->isEmployee()) {
            return $this->hasAdminApproval($request);
        }

        // Check if we're using dynamic rules
        $rules = ApprovalRule::where('department_id', $department->id)
            ->where('min_amount', '<=', $amount)
            ->where('max_amount', '>=', $amount)
            ->orderBy('order')
            ->get();

        if (!$rules->isEmpty()) {
            // Use dynamic rules to check if all required approvals are complete
            return $this->allDynamicApprovalsComplete($request, $rules);
        } else {
            // Use default workflow
            return $this->allDefaultApprovalsComplete($request);
        }
    }

    /**
     * Check if all dynamic approvals are complete
     */
    private function allDynamicApprovalsComplete(RequestModel $request, $rules): bool
    {
        $employee = $request->employee;

        foreach ($rules as $rule) {
            if (!$this->hasApprovalFromRole($request, $rule->approver_role, $employee->department_id)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check if all default approvals are complete
     */
    private function allDefaultApprovalsComplete(RequestModel $request): bool
    {
        $employee = $request->employee;
        $amount = $request->amount;

        // Get thresholds from settings
        $managerOnlyThreshold = SystemSetting::get('manager_only_threshold', 2000);
        $ceoThreshold = SystemSetting::get('ceo_approval_threshold', 5000);

        // Manager-only approval: only manager approval needed
        if ($amount <= $managerOnlyThreshold) {
            return $this->hasManagerApproval($request);
        }

        // Manager + CEO approval: both approvals needed
        if ($amount >= $ceoThreshold) {
            return $this->hasManagerApproval($request) && $this->hasAdminApproval($request);
        }

        // Fallback: manager approval required
        return $this->hasManagerApproval($request);
    }

    /**
     * Process next approval in the workflow
     */
    private function processNextApproval(RequestModel $request): void
    {
        // Get applicable workflow steps for this request
        $steps = WorkflowStep::getStepsForRequest($request);

        if ($steps->isEmpty()) {
            // Fallback to legacy system
            $this->processLegacyNextApproval($request);
            return;
        }

        // Process the next applicable step
        $this->processNextWorkflowStep($request, $steps);
    }

    /**
     * Legacy next approval processing
     */
    private function processLegacyNextApproval(RequestModel $request): void
    {
        $employee = $request->employee;
        $amount = $request->amount;
        $department = $employee->department;

        // Special handling for employee requests - only need CEO/Admin approval
        if ($employee->isEmployee()) {
            $autoApprovalThreshold = SystemSetting::get('auto_approval_threshold', 1000);

            // For amounts above threshold, check if Admin approval is needed
            if ($amount > $autoApprovalThreshold && !$this->hasAdminApproval($request)) {
                $admin = $this->getAdmin();
                if ($admin) {
                    $this->sendApprovalRequest($request, $admin, 'CEO/Admin approval required for employee request');
                }
            }
            return;
        }

        // Check if we're using dynamic rules
        $rules = ApprovalRule::where('department_id', $department->id)
            ->where('min_amount', '<=', $amount)
            ->where('max_amount', '>=', $amount)
            ->orderBy('order')
            ->get();

        if (!$rules->isEmpty()) {
            // Use dynamic rules for sequential approval
            $this->processNextDynamicApproval($request, $rules);
        } else {
            // Use default workflow
            $this->processNextDefaultApproval($request);
        }
    }

    /**
     * Process next approval using dynamic rules
     */
    private function processNextDynamicApproval(RequestModel $request, $rules): void
    {
        $employee = $request->employee;

        // Find the next approver in the sequence
        foreach ($rules as $rule) {
            $approver = $this->getApproverByRole($rule->approver_role, $employee->department_id);
            if ($approver && !$this->hasApprovalFromRole($request, $rule->approver_role, $employee->department_id)) {
                $this->sendApprovalRequest($request, $approver, "Approval required from {$rule->approver_role}");
                return;
            }
        }
    }

    /**
     * Process next approval using default workflow
     */
    private function processNextDefaultApproval(RequestModel $request): void
    {
        $employee = $request->employee;
        $amount = $request->amount;

        // Get thresholds from settings
        $managerOnlyThreshold = SystemSetting::get('manager_only_threshold', 2000);
        $ceoThreshold = SystemSetting::get('ceo_approval_threshold', 5000);

        // If amount requires CEO approval and we don't have it yet
        if ($amount >= $ceoThreshold && !$this->hasAdminApproval($request)) {
            $admin = $this->getAdmin();
            if ($admin) {
                $this->sendApprovalRequest($request, $admin, 'CEO approval required for high-value request');
            }
        }
    }

    /**
     * Auto-approve a request
     */
    private function autoApproveRequest(RequestModel $request): void
    {
        // Update request status to approved
        $request->update(['status' => 'Approved']);

        // Log the auto-approval
        $this->logAction(1, $request->id, 'Auto-Approved', 'Request auto-approved based on amount threshold');

        // Forward to Procurement
        $this->forwardToProcurement($request, 1);

        // Notify employee that request is approved
        $this->notificationService->sendEmployeeNotification($request, 'approved');
    }

    /**
     * Send approval request notification
     */
    private function sendApprovalRequest(RequestModel $request, User $approver, string $message): void
    {
        // Update request status to pending approval
        $request->update(['status' => 'Pending Approval']);

        // Log the approval request
        $this->logAction($approver->id, $request->id, 'Approval Request Sent', $message);

        // Send notification
        $this->notificationService->sendApprovalRequest($request, $approver, $message);
    }


    /**
     * Log action in audit log
     */
    private function logAction(int $userId, int $requestId, string $action, string $notes = null): void
    {
        AuditLog::create([
            'user_id' => $userId,
            'request_id' => $requestId,
            'action' => $action,
            'notes' => $notes
        ]);
    }

    /**
     * Helper methods
     */
    private function getDepartmentManager(int $departmentId): ?User
    {
        return User::where('department_id', $departmentId)
            ->whereHas('role', function($query) {
                $query->where('name', Role::MANAGER);
            })
            ->first();
    }

    private function getAdmin(): ?User
    {
        return User::whereHas('role', function($query) {
            $query->where('name', Role::ADMIN);
        })->first();
    }

    private function getApproverByRole(string $role, int $departmentId): ?User
    {
        if ($role === 'Manager' || $role === Role::MANAGER) {
            return User::whereHas('role', function($query) {
                $query->where('name', Role::MANAGER);
            })
            ->where('department_id', $departmentId)
            ->first();
        } elseif ($role === 'Admin' || $role === Role::ADMIN) {
            return User::whereHas('role', function($query) {
                $query->where('name', Role::ADMIN);
            })->first();
        } elseif ($role === 'Procurement' || $role === Role::PROCUREMENT) {
            return User::whereHas('role', function($query) {
                $query->where('name', Role::PROCUREMENT);
            })->first();
        }
        return null;
    }

    private function getProcurementUsers(): \Illuminate\Database\Eloquent\Collection
    {
        return User::whereHas('role', function($query) {
            $query->where('name', Role::PROCUREMENT);
        })->get();
    }

    private function canApprove(RequestModel $request, User $approver): bool
    {
        // Implement authorization logic based on role and department
        $employee = $request->employee;

        if ($approver->isAdmin()) {
            return true;
        } elseif ($approver->isManager()) {
            // Check if approver is from the same department as the employee
            if ($approver->department_id === $employee->department_id) {
                return true;
            }

            // Check if approver is assigned to this request in workflow steps
            return $this->isUserAssignedToRequest($request, $approver);
        } elseif ($approver->isProcurement()) {
            // Check if approver is assigned to this request in workflow steps
            return $this->isUserAssignedToRequest($request, $approver);
        }

        return false;
    }

    /**
     * Check if user is assigned to this request in workflow steps
     */
    private function isUserAssignedToRequest(RequestModel $request, User $user): bool
    {
        // Check if user is personally assigned to any workflow step
        $personalAssignment = WorkflowStepAssignment::where('assignable_type', 'App\\Models\\User')
            ->where('assignable_id', $user->id)
            ->whereHas('workflowStep', function($query) {
                $query->where('is_active', true);
            })
            ->exists();

        if ($personalAssignment) {
            return true;
        }

        // Check if user's department is assigned to any workflow step
        $departmentAssignment = WorkflowStepAssignment::where('assignable_type', 'App\\Models\\Department')
            ->where('assignable_id', $user->department_id)
            ->whereHas('workflowStep', function($query) {
                $query->where('is_active', true);
            })
            ->exists();

        if ($departmentAssignment) {
            return true;
        }

        // Check if user's role is assigned to any workflow step
        $roleAssignment = WorkflowStepAssignment::where('assignable_type', 'App\\Models\\Role')
            ->where('assignable_id', $user->role_id)
            ->whereHas('workflowStep', function($query) {
                $query->where('is_active', true);
            })
            ->exists();

        return $roleAssignment;
    }

    private function isPurchaseRelated(RequestModel $request): bool
    {
        // Simple logic to determine if request is purchase-related
        $purchaseKeywords = ['purchase', 'buy', 'order', 'equipment', 'supplies', 'materials'];
        $item = strtolower($request->item);
        $description = strtolower($request->description);

        foreach ($purchaseKeywords as $keyword) {
            if (strpos($item, $keyword) !== false || strpos($description, $keyword) !== false) {
                return true;
            }
        }

        return false;
    }

    private function hasManagerApproval(RequestModel $request): bool
    {
        return AuditLog::where('request_id', $request->id)
            ->where('action', 'Approved')
            ->whereHas('user', function($query) use ($request) {
                $query->whereHas('role', function($roleQuery) {
                    $roleQuery->where('name', Role::MANAGER);
                })
                ->where('department_id', $request->employee->department_id);
            })
            ->exists();
    }

    private function hasAdminApproval(RequestModel $request): bool
    {
        return AuditLog::where('request_id', $request->id)
            ->where('action', 'Approved')
            ->whereHas('user', function($query) {
                $query->whereHas('role', function($roleQuery) {
                    $roleQuery->where('name', Role::ADMIN);
                });
            })
            ->exists();
    }

    private function hasApprovalFromRole(RequestModel $request, string $role, int $departmentId): bool
    {
        $query = AuditLog::where('request_id', $request->id)
            ->where('action', 'Approved')
            ->whereHas('user', function($query) use ($role, $departmentId) {
                $query->whereHas('role', function($roleQuery) use ($role) {
                    // Handle both string and constant formats
                    if ($role === 'Manager' || $role === Role::MANAGER) {
                        $roleQuery->where('name', Role::MANAGER);
                    } elseif ($role === 'Admin' || $role === Role::ADMIN) {
                        $roleQuery->where('name', Role::ADMIN);
                    } elseif ($role === 'Procurement' || $role === Role::PROCUREMENT) {
                        $roleQuery->where('name', Role::PROCUREMENT);
                    } else {
                        $roleQuery->where('name', $role);
                    }
                });

                // For manager role, also check department
                if ($role === 'Manager' || $role === Role::MANAGER) {
                    $query->where('department_id', $departmentId);
                }
            });

        return $query->exists();
    }
}
