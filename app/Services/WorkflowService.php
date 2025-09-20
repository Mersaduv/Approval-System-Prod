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
use App\Models\Delegation;
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

            // Log: شروع workflow
            $this->logAction($employeeId, $request->id, 'Submitted', 'Request submitted - Workflow started');

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

        // Log: Step forwarded to Procurement Verification
        $this->logAction(999, $request->id, 'Step Forwarded', 'Request forwarded to: Procurement Verification');
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

            // If verified, proceed to approval workflow
            if ($status === 'Verified') {
                // Log: مرحله Procurement Verification تکمیل شده
                $this->logAction($procurementUserId, $requestId, 'Workflow Step Completed', "Procurement Verification - Status: {$status}" . ($notes ? " - {$notes}" : ""));

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

                // Log: مرحله Procurement Verification رد شده
                $this->logAction(
                    $procurementUserId,
                    $requestId,
                    'Workflow Step Rejected',
                    "Procurement Verification - {$procurementUser->name} rejected: " . ($notes ?: 'No reason provided')
                );

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
    public function processNextWorkflowStep(RequestModel $request, $steps): void
    {
        $processed = false;
        $nextStep = null;

        // Check if the request creator is admin
        $isAdminRequest = $request->employee && $request->employee->role && $request->employee->role->name === 'admin';

        // Get all steps including auto approve steps for processing
        $allSteps = WorkflowStep::getAllStepsForRequest($request);

        foreach ($allSteps as $step) {
            // Skip manager assignment steps for admin requests
            if ($isAdminRequest && WorkflowStep::isManagerAssignmentStep($step)) {
                continue;
            }

            if ($this->shouldProcessStep($request, $step)) {
                $nextStep = $step;
                $this->executeWorkflowStep($request, $step);
                $processed = true;
                break; // Only process one step at a time
            }
        }

        // Log which step the request was forwarded to (except for Procurement Verification which is already logged)
        if ($processed && $nextStep && $nextStep->name !== 'Procurement Verification') {
            $this->logAction(999, $request->id, 'Step Forwarded', "Request forwarded to: {$nextStep->name}");
        }

        // If no step was processed, check if all approvals are complete
        if (!$processed && $this->allApprovalsComplete($request)) {
            $request->update(['status' => 'Approved']);
            $this->logAction(999, $request->id, 'All Approvals Complete', 'All required approvals have been completed');
        }
    }

    /**
     * Check if a step should be processed
     */
    public function shouldProcessStep(RequestModel $request, WorkflowStep $step): bool
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

        // Check if step has already been started (to prevent duplicates)
        if ($this->isStepStarted($request, $step)) {
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
        if ($step->auto_approve) {
            $this->autoApproveRequest($request, $step);
            return;
        }

        // Get assigned approvers with delegation support
        $approvers = $this->getEffectiveApprovers($request, $step);

        if ($approvers->isEmpty()) {
            Log::warning("No approvers assigned to workflow step: {$step->name}");
            return;
        }

        // Send approval request to ALL approvers
        foreach ($approvers as $approver) {
            $this->sendApprovalRequest($request, $approver, $step->name);
        }

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
        $users = $step->getAssignedUsers($request);

        foreach ($users as $user) {
            $this->notificationService->sendEmployeeNotification($request, 'notification', $user);
        }

        // Mark step as completed and move to next
        $this->markStepCompleted($request, $step);
        $this->processNextWorkflowStep($request, WorkflowStep::getStepsForRequest($request));
    }

    /**
     * Get current step for approver
     */
    public function getCurrentStepForApprover(RequestModel $request, User $approver): ?WorkflowStep
    {
        $steps = WorkflowStep::getStepsForRequest($request);

        // Find the first incomplete step for this approver in the correct order
        foreach ($steps as $step) {
            if ($this->isStepForUser($step, $approver)) {
                // Check if this step is completed
                if ($this->isStepCompleted($request, $step)) {
                    continue; // Skip completed steps
                }

                // Check if this step is rejected
                if ($this->isStepRejected($request, $step)) {
                    continue; // Skip rejected steps
                }

                // Check if all previous steps are completed
                if ($this->arePreviousStepsCompleted($request, $step, $steps)) {
                    return $step; // This is the current step for this user
                }
            }
        }

        return null;
    }

    /**
     * Check if all previous steps (in order) are completed
     */
    private function arePreviousStepsCompleted(RequestModel $request, WorkflowStep $currentStep, $steps): bool
    {
        foreach ($steps as $step) {
            // If we've reached the current step, all previous steps are completed
            if ($step->id === $currentStep->id) {
                return true;
            }

            // If this step is required and not completed, we can't proceed to current step
            if ($step->is_required && !$this->isStepCompleted($request, $step)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check if step is rejected
     */
    private function isStepRejected(RequestModel $request, WorkflowStep $step): bool
    {
        // Check if step is specifically rejected in audit logs
        $stepRejected = \App\Models\AuditLog::where('request_id', $request->id)
            ->where('action', 'Workflow Step Rejected')
            ->where('notes', 'like', '%' . $step->name . '%')
            ->exists();

        if ($stepRejected) {
            return true;
        }

        // Check for verification step rejection
        if ($step->step_type === 'verification') {
            // For procurement verification, check if it was rejected
            if ($request->procurement_status === 'Rejected' || $request->procurement_status === 'Not Available') {
                return true;
            }
        }

        return false;
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
    public function isStepCompleted(RequestModel $request, WorkflowStep $step): bool
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

        // For approval steps, check if all required assignments are completed
        if ($step->step_type === 'approval') {
            return $this->areAllRequiredAssignmentsCompleted($request, $step);
        }

        // For verification steps, check if procurement verification is completed
        if ($step->step_type === 'verification') {
            return $request->procurement_status === 'Verified';
        }

        return false;
    }

    /**
     * Check if a step has already been started (sent to users)
     */
    private function isStepStarted(RequestModel $request, WorkflowStep $step): bool
    {
        // Check if there are any "Workflow Step Started" logs for this step
        return AuditLog::where('request_id', $request->id)
            ->where('action', 'Workflow Step Started')
            ->where('notes', 'like', '%' . $step->name . '%')
            ->exists();
    }

    /**
     * Check if all required assignments for a step are completed
     */
    private function areAllRequiredAssignmentsCompleted(RequestModel $request, WorkflowStep $step): bool
    {
        // Get all required assignments for this step
        $requiredAssignments = $step->assignments()->where('is_required', true)->get();

        if ($requiredAssignments->isEmpty()) {
            // If no required assignments, check if any assignment has approved
            $anyAssignmentApproved = $step->assignments()->exists();
            if ($anyAssignmentApproved) {
                return $this->hasAnyAssignmentApproved($request, $step);
            }
            return true; // No assignments means step is complete
        }

        // Check if all required assignments have been approved
        foreach ($requiredAssignments as $assignment) {
            if (!$this->isAssignmentApproved($request, $assignment)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check if a specific assignment has been approved
     */
    private function isAssignmentApproved(RequestModel $request, WorkflowStepAssignment $assignment): bool
    {
        // Get all users for this assignment
        $users = $assignment->getUsers();

        if ($users->isEmpty()) {
            return false;
        }

        // Count how many users have approved this assignment
        $approvedCount = 0;
        foreach ($users as $user) {
            if ($this->hasUserApprovedStep($request, $user, $assignment->workflowStep)) {
                $approvedCount++;
            }
        }

        // Check if enough users have approved based on priority
        // Priority indicates how many users need to approve
        $requiredApprovals = $assignment->priority ?: 1;

        return $approvedCount >= $requiredApprovals;
    }

    /**
     * Check if a user has approved a specific step
     */
    public function hasUserApprovedStep(RequestModel $request, User $user, WorkflowStep $step): bool
    {
        // Check for specific step approval - look for the actual log pattern used
        return AuditLog::where('request_id', $request->id)
            ->where('user_id', $user->id)
            ->where('action', 'Workflow Step Completed')
            ->where('notes', 'like', '%' . $step->name . '%')
            ->where('notes', 'like', '%approved%')
            ->exists();
    }

    /**
     * Check if any assignment has been approved (for steps with no required assignments)
     */
    private function hasAnyAssignmentApproved(RequestModel $request, WorkflowStep $step): bool
    {
        $allAssignments = $step->assignments;

        foreach ($allAssignments as $assignment) {
            if ($this->isAssignmentApproved($request, $assignment)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Mark step as completed
     */
    private function markStepCompleted(RequestModel $request, WorkflowStep $step): void
    {
        $this->logAction(1, $request->id, 'Step completed', "Step completed: {$step->name} (Step ID: {$step->id})");
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
            $this->logAction(
                $approverId,
                $request->id,
                'Step completed',
                "Step completed: {$currentStep->name} by {$approver->name} (Step ID: {$currentStep->id})"
            );
        }
    }

    /**
     * Check if step is assigned to user
     */
    public function isStepForUser(WorkflowStep $step, User $user): bool
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
            case 'App\\Models\\FinanceAssignment':
                $financeAssignment = \App\Models\FinanceAssignment::find($assignment->assignable_id);
                return $financeAssignment && $financeAssignment->user_id == $user->id && $financeAssignment->is_active;
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
                $this->logAction(
                    $rejectorId,
                    $request->id,
                    'Step Rejected',
                    "Step rejected: {$step->name}"
                );
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
            $this->legacyAutoApproveRequest($request);
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
            $this->legacyAutoApproveRequest($request);
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

            // Log: مرحله workflow تکمیل شده
            $this->logAction(
                $approverId,
                $requestId,
                'Workflow Step Completed',
                $notes ? "{$stepName} - {$approver->name} approved: {$notes}" : "{$stepName} - {$approver->name} approved"
            );

            // Check if the current step is now completed (all required assignments approved)
            if ($currentStep && $this->isStepCompleted($request, $currentStep)) {
                // Mark the step as completed
                $this->markStepCompleted($request, $currentStep);

                // Process next workflow step
                $this->processNextWorkflowStep($request, WorkflowStep::getStepsForRequest($request));
            } else {
                // If current step is not completed, continue with next approver in the sequential chain
                $this->processNextApproval($request);
            }

            // Check if all required approvals are complete
            if ($this->allApprovalsComplete($request)) {
                // Update request status to Approved (all approvals complete, ready for procurement)
                $request->update(['status' => 'Approved']);

                // Log: تکمیل تمام مراحل workflow
                $this->logAction($approverId, $request->id, 'All Approvals Complete', 'All workflow steps completed - Ready for procurement');

                // Notify employee that request is approved
                $this->notificationService->sendEmployeeNotification($request, 'approved');
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

            // Get current step information
            $currentStep = $this->getCurrentStepForApprover($request, $rejector);
            $stepName = $currentStep ? $currentStep->name : 'Unknown Step';
            $stepId = $currentStep ? $currentStep->id : 'Unknown';

            // Update request status
            $request->update(['status' => 'Rejected']);

            // Log: مرحله workflow رد شده
            $this->logAction(
                $rejectorId,
                $requestId,
                'Workflow Step Rejected',
                "{$stepName} - {$rejector->name} rejected: {$reason}"
            );

            // Step is already marked as rejected in the main rejection log

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
            if (!in_array($request->status, ['Approved', 'Pending Procurement', 'Ordered', 'Pending Approval'])) {
                throw new \Exception('Request is not in a valid status for procurement processing');
            }

            $procurement = $request->procurement;
            if (!$procurement) {
                // Create procurement record if it doesn't exist
                $initialStatus = in_array($request->status, ['Approved', 'Pending Approval']) ? 'Pending Procurement' : 'Ordered';
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
                // If request was in Pending Approval, first approve it, then set to Ordered
                if ($request->status === 'Pending Approval') {
                    $request->update(['status' => 'Approved']);
                    // Complete the current workflow step
                    $this->completeCurrentWorkflowStep($request, $procurementUserId, 'approved');
                }
                $request->update(['status' => 'Ordered']);
                $this->notificationService->sendEmployeeNotification($request, 'ordered');
            } elseif ($status === 'Delivered') {
                $request->update(['status' => 'Delivered']);
                $this->notificationService->sendEmployeeNotification($request, 'delivered');
            } elseif ($status === 'Cancelled') {
                // If request was in Pending Approval, first approve it, then set to Cancelled
                if ($request->status === 'Pending Approval') {
                    $request->update(['status' => 'Approved']);
                    // Complete the current workflow step
                    $this->completeCurrentWorkflowStep($request, $procurementUserId, 'approved');
                }
                $request->update(['status' => 'Cancelled']);
                $this->notificationService->sendEmployeeNotification($request, 'cancelled', $notes);

                // Log the cancellation action
                $this->logAction($procurementUserId, $requestId, 'Workflow Step Cancelled', "Procurement order cancelled: " . ($notes ?: 'No reason provided'));
            } else {
                // Log the action for other statuses
                $this->logAction($procurementUserId, $requestId, 'Procurement Processed', "Status: {$status}" . ($notes ? " - {$notes}" : ""));
            }

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
    public function allApprovalsComplete(RequestModel $request): bool
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
     * Auto-approve a request (for workflow steps)
     */
    private function autoApproveRequest(RequestModel $request, WorkflowStep $step): void
    {
        // Note: "Workflow Step Started" is now handled by "Workflow Step Forwarded"
        // to avoid duplicate logging in audit trail

        // Update request status to approved
        $request->update(['status' => 'Approved']);

        // Log: مرحله Auto-Approval تکمیل شده
        $this->logAction(999, $request->id, 'Workflow Step Completed', "Auto-approval step completed: {$step->name}");

        // Notify employee that request is approved
        $this->notificationService->sendEmployeeNotification($request, 'approved');
    }

    /**
     * Legacy auto-approve a request (for amount-based auto approval)
     */
    private function legacyAutoApproveRequest(RequestModel $request): void
    {
        // Update request status to approved
        $request->update(['status' => 'Approved']);

        // Log: مرحله Auto-Approval تکمیل شده
        $this->logAction(999, $request->id, 'Workflow Step Completed', 'Auto-Approval - Request auto-approved based on amount threshold');

        // Notify employee that request is approved
        $this->notificationService->sendEmployeeNotification($request, 'approved');
    }

    /**
     * Delay a workflow step
     */
    public function delayWorkflowStep(int $requestId, int $userId, string $delayDate, string $delayReason = null): bool
    {
        return DB::transaction(function () use ($requestId, $userId, $delayDate, $delayReason) {
            $request = RequestModel::findOrFail($requestId);
            $user = User::findOrFail($userId);

            // Get the current step for this user
            $currentStep = $this->getCurrentStepForApprover($request, $user);

            if (!$currentStep) {
                throw new \Exception('No active workflow step found for this user');
            }

            // Log: مرحله workflow delayed شده
            $this->logAction(
                $userId,
                $requestId,
                'Workflow Step Delayed',
                "Step delayed: {$currentStep->name} - Delayed until {$delayDate}" .
                ($delayReason ? " - Reason: {$delayReason}" : "")
            );

            return true;
        });
    }

    /**
     * Send approval request notification
     */
    private function sendApprovalRequest(RequestModel $request, User $approver, string $stepName): void
    {
        // Update request status to pending approval
        $request->update(['status' => 'Pending Approval']);

        // Note: "Workflow Step Started" is now handled by "Workflow Step Forwarded"
        // to avoid duplicate logging in audit trail

        // Send notification
        $this->notificationService->sendApprovalRequest($request, $approver, "Approval required: {$stepName}");
    }


    /**
     * Log action in audit log
     */
    private function logAction(int $userId, int $requestId, string $action, string $notes = null): void
    {
        // Use System user (ID: 999) for automated workflow actions
        $systemUserId = 999;

        AuditLog::create([
            'user_id' => $userId === 1 ? $systemUserId : $userId, // Replace admin (1) with system (999)
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

    public function canApprove(RequestModel $request, User $approver): bool
    {
        // Implement authorization logic based on role and department
        $employee = $request->employee;

        // Get applicable workflow steps for this request
        $steps = WorkflowStep::getStepsForRequest($request);

        if ($steps->isEmpty()) {
            // Fallback to legacy system
            return $this->canApproveLegacy($request, $approver);
        }

        // For dynamic workflow, check if user is assigned to the current pending step
        $currentStep = $this->getCurrentStepForApprover($request, $approver);

        if (!$currentStep) {
            // User is not assigned to any pending step
            return false;
        }

        // Check if this is the user's turn to approve (step is not completed yet)
        if ($this->isStepCompleted($request, $currentStep)) {
            return false;
        }

        // Check if user is assigned to this specific step
        return $this->isStepForUser($currentStep, $approver);
    }

    /**
     * Legacy approval authorization
     */
    private function canApproveLegacy(RequestModel $request, User $approver): bool
    {
        $employee = $request->employee;

        if ($approver->isAdmin()) {
            // Admin can always approve in legacy mode
            return true;
        } elseif ($approver->isManager()) {
            // Check if approver is from the same department as the employee
            return $approver->department_id === $employee->department_id;
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

    /**
     * Complete the current workflow step
     */
    private function completeCurrentWorkflowStep(RequestModel $request, int $userId, string $action): void
    {
        // Check if approval_workflow exists
        if (!$request->approval_workflow) {
            // Log the workflow step completion without workflow data
            $this->logAction($userId, $request->id, 'Workflow Step Completed', "Step completed: Procurement order - {$action}");
            return;
        }

        // Log the workflow step completion
        $this->logAction($userId, $request->id, 'Workflow Step Completed', "Step completed: {$request->approval_workflow->waiting_for} - {$action}");

        // Update workflow status
        $request->approval_workflow->update([
            'waiting_for' => null,
            'can_approve' => false
        ]);
    }

    /**
     * Get effective approvers considering delegations
     */
    private function getEffectiveApprovers(RequestModel $request, WorkflowStep $step): \Illuminate\Database\Eloquent\Collection
    {
        // Get original approvers from workflow step assignments
        $originalApprovers = $step->getAssignedUsers($request);
        $effectiveApprovers = collect();

        foreach ($originalApprovers as $approver) {
            // Determine delegation type based on step type
            $delegationTypes = ['all']; // Always include 'all' type
            switch ($step->step_type) {
                case 'approval':
                    $delegationTypes[] = 'approval';
                    break;
                case 'verification':
                    $delegationTypes[] = 'verification';
                    break;
                case 'notification':
                    $delegationTypes[] = 'notification';
                    break;
            }

            // Check if this approver has active delegations for this specific step
            $activeDelegations = Delegation::where('delegator_id', $approver->id)
                ->where('is_active', true)
                ->where('workflow_step_id', $step->id) // Only specific step delegations
                ->where(function ($query) use ($request) {
                    $query->whereNull('department_id')
                        ->orWhere('department_id', $request->employee->department_id);
                })
                ->where(function ($query) {
                    $query->whereNull('starts_at')
                        ->orWhere('starts_at', '<=', now());
                })
                ->where(function ($query) {
                    $query->whereNull('expires_at')
                        ->orWhere('expires_at', '>', now());
                })
                ->whereIn('delegation_type', $delegationTypes)
                ->get();

            if ($activeDelegations->isNotEmpty()) {
                // Add delegates instead of original approver
                foreach ($activeDelegations as $delegation) {
                    $effectiveApprovers->push($delegation->delegate);

                    // Log the delegation usage
                    $this->logAction(
                        $delegation->delegate_id,
                        $request->id,
                        'Delegation Applied',
                        "Acting on behalf of {$approver->full_name} for step: {$step->name}"
                    );
                }
            } else {
                // No delegation, use original approver
                $effectiveApprovers->push($approver);
            }
        }

        return $effectiveApprovers->unique('id');
    }

    /**
     * Check if user can act on behalf of another user for a specific request
     */
    public function canActOnBehalfOf(User $delegate, User $originalApprover, RequestModel $request, WorkflowStep $step): bool
    {
        return Delegation::where('delegator_id', $originalApprover->id)
            ->where('delegate_id', $delegate->id)
            ->where('is_active', true)
            ->where(function ($query) use ($step) {
                $query->whereNull('workflow_step_id')
                    ->orWhere('workflow_step_id', $step->id);
            })
            ->where(function ($query) use ($request) {
                $query->whereNull('department_id')
                    ->orWhere('department_id', $request->employee->department_id);
            })
            ->where(function ($query) {
                $query->whereNull('starts_at')
                    ->orWhere('starts_at', '<=', now());
            })
            ->where(function ($query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->where('delegation_type', 'approval')
            ->exists();
    }
}
