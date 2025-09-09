<?php

namespace App\Services;

use App\Models\Request as RequestModel;
use App\Models\User;
use App\Models\Role;
use App\Models\ApprovalRule;
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
                'status' => 'Pending'
            ]);

            // Log the submission
            $this->logAction($employeeId, $request->id, 'Submitted', 'Request submitted for approval');

            // Start the approval workflow
            $this->processApprovalWorkflow($request);

            return $request;
        });
    }

    /**
     * Process the approval workflow based on rules
     */
    public function processApprovalWorkflow(RequestModel $request): void
    {
        $employee = $request->employee;
        $department = $employee->department;
        $amount = $request->amount;

        // Special handling for procurement users - they need CEO/Admin approval
        if ($employee->isProcurement()) {
            $this->processProcurementWorkflow($request);
            return;
        }

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
     * Process workflow for procurement users - they need CEO/Admin approval
     */
    private function processProcurementWorkflow(RequestModel $request): void
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

        // For procurement users, all requests above auto-approval threshold need CEO/Admin approval
        $admin = $this->getAdmin();
        if ($admin) {
            $this->sendApprovalRequest($request, $admin, 'CEO approval required for procurement request');
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

            // Log the approval
            $this->logAction($approverId, $requestId, 'Approved', $notes);

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
        $employee = $request->employee;
        $amount = $request->amount;
        $department = $employee->department;

        // Get thresholds from settings
        $autoApprovalThreshold = SystemSetting::get('auto_approval_threshold', 1000);

        // Auto-approval: no additional approvals needed
        if ($amount <= $autoApprovalThreshold) {
            return true;
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
        $employee = $request->employee;
        $amount = $request->amount;
        $department = $employee->department;

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
            return $approver->department_id === $employee->department_id;
        }

        return false;
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
