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
        // Step 1: Manager approval
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

        // Step 2: CEO approval (if amount exceeds CEO threshold)
        if ($amount >= $ceoThreshold) {
            $admin = $this->getAdmin();
            if ($admin) {
                $this->sendApprovalRequest($request, $admin, 'CEO approval required for high-value request');
            }
        }
    }

    /**
     * Process dynamic workflow based on rules
     */
    private function processDynamicWorkflow(RequestModel $request, $rules): void
    {
        foreach ($rules as $rule) {
            $approver = $this->getApproverByRole($rule->approver_role, $request->employee->department_id);
            if ($approver) {
                $this->sendApprovalRequest($request, $approver, "Approval required from {$rule->approver_role}");
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

            // Log the approval
            $this->logAction($approverId, $requestId, 'Approved', $notes);

            // For our simplified system, if manager approves, it's automatically approved
            // If admin approves, it's automatically approved
            if ($approver->isManager() || $approver->isAdmin()) {
                // Forward to Procurement
                $this->forwardToProcurement($request, $approverId);

                // Notify employee that request is approved
                $this->notificationService->sendEmployeeNotification($request, 'approved');
            } else {
                // Check if all required approvals are complete
                if ($this->allApprovalsComplete($request)) {
                    // Forward to Procurement
                    $this->forwardToProcurement($request, $approverId);

                    // Notify employee that request is approved
                    $this->notificationService->sendEmployeeNotification($request, 'approved');
                } else {
                    // Continue with next approver
                    $this->processNextApproval($request);
                }
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

            // Check if request is in correct status (either Approved or Pending Procurement)
            if (!in_array($request->status, ['Approved', 'Pending Procurement'])) {
                throw new \Exception('Request is not in a valid status for procurement processing');
            }

            $procurement = $request->procurement;
            if (!$procurement) {
                // Create procurement record if it doesn't exist (for Approved requests)
                $procurement = Procurement::create([
                    'request_id' => $request->id,
                    'status' => 'Pending Procurement'
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

        // Get thresholds from settings
        $autoApprovalThreshold = SystemSetting::get('auto_approval_threshold', 1000);
        $managerOnlyThreshold = SystemSetting::get('manager_only_threshold', 2000);
        $ceoThreshold = SystemSetting::get('ceo_approval_threshold', 5000);

        // Auto-approval: no additional approvals needed
        if ($amount <= $autoApprovalThreshold) {
            return true;
        }

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
        if ($role === Role::MANAGER) {
            return User::whereHas('role', function($query) {
                $query->where('name', Role::MANAGER);
            })
            ->where('department_id', $departmentId)
            ->first();
        } elseif ($role === Role::ADMIN) {
            return User::whereHas('role', function($query) {
                $query->where('name', Role::ADMIN);
            })->first();
        } elseif ($role === Role::PROCUREMENT) {
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
}
