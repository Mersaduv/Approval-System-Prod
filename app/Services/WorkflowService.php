<?php

namespace App\Services;

use App\Models\Request as RequestModel;
use App\Models\User;
use App\Models\ApprovalRule;
use App\Models\Notification;
use App\Models\AuditLog;
use App\Models\Procurement;
use App\Models\ApprovalToken;
use App\Mail\ApprovalNotificationMail;
use App\Mail\EmployeeNotificationMail;
use App\Services\NotificationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class WorkflowService
{
    const CEO_THRESHOLD = 5000; // AFN

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

        // Step 1: Manager approval
        $manager = $this->getDepartmentManager($employee->department_id);
        if ($manager) {
            $this->sendApprovalRequest($request, $manager, 'Manager approval required');
        }

        // Step 2: Sales Manager approval (if purchase-related)
        if ($this->isPurchaseRelated($request)) {
            $salesManager = $this->getSalesManager();
            if ($salesManager) {
                $this->sendApprovalRequest($request, $salesManager, 'Sales Manager approval required for purchase');
            }
        }

        // Step 3: CEO approval (if amount exceeds threshold)
        if ($amount >= self::CEO_THRESHOLD) {
            $ceo = $this->getCEO();
            if ($ceo) {
                $this->sendApprovalRequest($request, $ceo, 'CEO approval required for high-value request');
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

            // Check if all required approvals are complete
            if ($this->allApprovalsComplete($request)) {
                // Forward to Procurement
                $this->forwardToProcurement($request);

                // Notify employee that request is approved
                $this->notificationService->sendEmployeeNotification($request, 'approved');
            } else {
                // Continue with next approver
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
    private function forwardToProcurement(RequestModel $request): void
    {
        // Create procurement record
        Procurement::create([
            'request_id' => $request->id,
            'status' => 'Ordered'
        ]);

        // Update request status
        $request->update(['status' => 'Approved']);

        // Notify procurement team
        $this->notificationService->sendProcurementNotification($request);

        // Log the action
        $this->logAction(1, $request->id, 'Forwarded to Procurement', 'Request approved and forwarded to procurement team');
    }

    /**
     * Update procurement status
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

        // Check manager approval
        if (!$this->hasManagerApproval($request)) {
            return false;
        }

        // Check sales manager approval (if required)
        if ($this->isPurchaseRelated($request) && !$this->hasSalesManagerApproval($request)) {
            return false;
        }

        // Check CEO approval (if required)
        if ($amount >= self::CEO_THRESHOLD && !$this->hasCEOApproval($request)) {
            return false;
        }

        return true;
    }

    /**
     * Process next approval in the workflow
     */
    private function processNextApproval(RequestModel $request): void
    {
        $employee = $request->employee;
        $amount = $request->amount;

        // Check if sales manager approval is needed
        if ($this->isPurchaseRelated($request) && !$this->hasSalesManagerApproval($request)) {
            $salesManager = $this->getSalesManager();
            if ($salesManager) {
                $this->sendApprovalRequest($request, $salesManager, 'Sales Manager approval required for purchase');
            }
        }

        // Check if CEO approval is needed
        if ($amount >= self::CEO_THRESHOLD && !$this->hasCEOApproval($request)) {
            $ceo = $this->getCEO();
            if ($ceo) {
                $this->sendApprovalRequest($request, $ceo, 'CEO approval required for high-value request');
            }
        }
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
            ->where('role', 'Manager')
            ->first();
    }

    private function getSalesManager(): ?User
    {
        return User::where('role', 'SalesManager')->first();
    }

    private function getCEO(): ?User
    {
        return User::where('role', 'CEO')->first();
    }

    private function getApproverByRole(string $role, int $departmentId): ?User
    {
        if ($role === 'Manager') {
            return User::where('role', 'Manager')
                ->where('department_id', $departmentId)
                ->first();
        } elseif (in_array($role, ['SalesManager', 'CEO', 'Procurement', 'Admin'])) {
            return User::where('role', $role)->first();
        }
        return null;
    }

    private function canApprove(RequestModel $request, User $approver): bool
    {
        // Implement authorization logic based on role and department
        $employee = $request->employee;

        switch ($approver->role) {
            case 'Manager':
                return $approver->department_id === $employee->department_id;
            case 'SalesManager':
                return $this->isPurchaseRelated($request);
            case 'CEO':
                return $request->amount >= self::CEO_THRESHOLD;
            case 'Admin':
                return true;
            default:
                return false;
        }
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
                $query->where('role', 'Manager')
                      ->where('department_id', $request->employee->department_id);
            })
            ->exists();
    }

    private function hasSalesManagerApproval(RequestModel $request): bool
    {
        return AuditLog::where('request_id', $request->id)
            ->where('action', 'Approved')
            ->whereHas('user', function($query) {
                $query->where('role', 'SalesManager');
            })
            ->exists();
    }

    private function hasCEOApproval(RequestModel $request): bool
    {
        return AuditLog::where('request_id', $request->id)
            ->where('action', 'Approved')
            ->whereHas('user', function($query) {
                $query->where('role', 'CEO');
            })
            ->exists();
    }
}
