<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\WorkflowService;
use App\Models\Request as RequestModel;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class RequestController extends Controller
{
    protected $workflowService;

    public function __construct(WorkflowService $workflowService)
    {
        $this->workflowService = $workflowService;
    }

    /**
     * Display a listing of requests
     */
    public function index(Request $request): JsonResponse
    {
        $user = Auth::user();
        $query = RequestModel::with(['employee', 'employee.department', 'procurement', 'notifications']);

        // Filter based on user role
        switch ($user->role->name) {
            case 'employee':
                $query->where('employee_id', $user->id);
                break;
            case 'manager':
                $query->whereHas('employee', function($q) use ($user) {
                    $q->where('department_id', $user->department_id);
                });
                break;
            case 'procurement':
                // Procurement users can only see their own requests
                $query->where('employee_id', $user->id);
                break;
            case 'admin':
                // Can see all requests
                break;
        }

        // Apply filters
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('department_id')) {
            $query->whereHas('employee', function($q) use ($request) {
                $q->where('department_id', $request->department_id);
            });
        }

        $requests = $query->orderBy('created_at', 'desc')->paginate(15);

        return response()->json([
            'success' => true,
            'data' => $requests
        ]);
    }

    /**
     * Store a newly created request
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'item' => 'required|string|max:255',
            'description' => 'required|string',
            'amount' => 'required|numeric|min:0'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $requestData = $request->only(['item', 'description', 'amount']);
            $newRequest = $this->workflowService->submitRequest($requestData, Auth::id());

            return response()->json([
                'success' => true,
                'message' => 'Request submitted successfully',
                'data' => $newRequest->load(['employee', 'employee.department'])
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to submit request',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified request
     */
    public function show(string $id): JsonResponse
    {
        $request = RequestModel::with([
            'employee',
            'employee.department',
            'procurement',
            'notifications.receiver',
            'auditLogs.user'
        ])->findOrFail($id);

        // Check if user can view this request
        if (!$this->canViewRequest($request, Auth::user())) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to view this request'
            ], 403);
        }

        // Add approval workflow information
        $request->approval_workflow = $this->getApprovalWorkflowInfo($request);

        return response()->json([
            'success' => true,
            'data' => $request
        ]);
    }

    /**
     * Approve a request
     */
    public function approve(Request $request, string $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'notes' => 'nullable|string|max:1000'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $this->workflowService->approveRequest(
                $id,
                Auth::id(),
                $request->input('notes')
            );

            return response()->json([
                'success' => true,
                'message' => 'Request approved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to approve request',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reject a request
     */
    public function reject(Request $request, string $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'reason' => 'required|string|max:1000'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $this->workflowService->rejectRequest(
                $id,
                Auth::id(),
                $request->input('reason')
            );

            return response()->json([
                'success' => true,
                'message' => 'Request rejected successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to reject request',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update procurement status (legacy method for admin)
     */
    public function updateProcurement(Request $request, string $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'status' => 'required|in:Ordered,Delivered,Failed',
            'final_cost' => 'nullable|numeric|min:0'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Check if user is admin (only admin can update procurement status in our simplified system)
        if (Auth::user()->role->name !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Only admin can update procurement status'
            ], 403);
        }

        try {
            $this->workflowService->updateProcurementStatus(
                $id,
                $request->input('status'),
                $request->input('final_cost')
            );

            return response()->json([
                'success' => true,
                'message' => 'Procurement status updated successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update procurement status',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Process procurement approval (for procurement users)
     */
    public function processProcurement(Request $request, string $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'status' => 'required|in:Pending Procurement,Ordered,Delivered,Cancelled',
            'final_cost' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string|max:1000'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Check if user can process procurement
        /** @var \App\Models\User $user */
        $user = Auth::user();
        if (!$user || !$user->canProcessProcurement()) {
            return response()->json([
                'success' => false,
                'message' => 'Only procurement team members can process procurement requests'
            ], 403);
        }

        try {
            $this->workflowService->processProcurementApproval(
                $id,
                Auth::id(),
                $request->input('status'),
                $request->input('final_cost'),
                $request->input('notes')
            );

            return response()->json([
                'success' => true,
                'message' => 'Procurement request processed successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to process procurement request',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get procurement requests (all approved requests for procurement team)
     */
    public function pendingProcurement(Request $request): JsonResponse
    {
        try {
            // Check if user can process procurement
            /** @var \App\Models\User $user */
            $user = Auth::user();
            if (!$user || !$user->canProcessProcurement()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Only procurement team members can view procurement requests'
                ], 403);
            }

            // Get all approved requests that are in procurement workflow
            $query = RequestModel::whereIn('status', ['Approved', 'Pending Procurement', 'Ordered', 'Delivered', 'Cancelled'])
                ->with(['employee.department', 'procurement']);

            // Apply status filter if provided
            if ($request->has('status') && $request->status !== 'all') {
                $query->where('status', $request->status);
            }

            $requests = $query->orderBy('created_at', 'desc')->get();

            return response()->json([
                'success' => true,
                'data' => $requests
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch procurement requests',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get requests pending approval for current user
     */
    public function pendingApprovals(): JsonResponse
    {
        $user = Auth::user();

        $query = RequestModel::with(['employee', 'employee.department'])
            ->where('status', 'Pending');

        // Filter based on user role and department
        switch ($user->role->name) {
            case 'manager':
                $query->whereHas('employee', function($q) use ($user) {
                    $q->where('department_id', $user->department_id);
                });
                break;
            case 'admin':
                // Can see all pending requests
                break;
            default:
                return response()->json([
                    'success' => false,
                    'message' => 'No pending approvals for your role'
                ], 403);
        }

        $requests = $query->orderBy('created_at', 'asc')->get();

        return response()->json([
            'success' => true,
            'data' => $requests
        ]);
    }

    /**
     * Rollback a cancelled request
     */
    public function rollbackRequest(Request $request, string $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'notes' => 'nullable|string|max:1000'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Check if user can process procurement
        /** @var \App\Models\User $user */
        $user = Auth::user();
        if (!$user || !$user->canProcessProcurement()) {
            return response()->json([
                'success' => false,
                'message' => 'Only procurement team members can rollback requests'
            ], 403);
        }

        try {
            $this->workflowService->rollbackCancelledRequest(
                $id,
                Auth::id(),
                $request->input('notes')
            );

            return response()->json([
                'success' => true,
                'message' => 'Request restored successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to restore request',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get audit logs for a request
     */
    public function auditLogs(string $id): JsonResponse
    {
        $request = RequestModel::findOrFail($id);

        // Check if user can view this request
        if (!$this->canViewRequest($request, Auth::user())) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to view this request'
            ], 403);
        }

        $auditLogs = $request->auditLogs()
            ->with('user')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $auditLogs
        ]);
    }

    /**
     * Get approval workflow information for a request
     */
    private function getApprovalWorkflowInfo(RequestModel $request): array
    {
        $employee = $request->employee;
        $department = $employee->department;
        $amount = $request->amount;

        // Get approval rules for this department
        $rules = \App\Models\ApprovalRule::where('department_id', $department->id)
            ->where('min_amount', '<=', $amount)
            ->where('max_amount', '>=', $amount)
            ->orderBy('order')
            ->get();

        $workflowInfo = [
            'is_sequential' => $rules->count() > 1,
            'current_step' => 1,
            'total_steps' => $rules->count(),
            'steps' => [],
            'next_approver' => null,
            'waiting_for' => null
        ];

        if ($rules->isEmpty()) {
            // Use default workflow
            $workflowInfo = $this->getDefaultWorkflowInfo($request);
        } else {
            // Use dynamic rules
            $workflowInfo = $this->getDynamicWorkflowInfo($request, $rules);
        }

        return $workflowInfo;
    }

    /**
     * Get default workflow information
     */
    private function getDefaultWorkflowInfo(RequestModel $request): array
    {
        $employee = $request->employee;
        $amount = $request->amount;

        // Get thresholds from settings
        $autoApprovalThreshold = \App\Models\SystemSetting::get('auto_approval_threshold', 1000);
        $managerOnlyThreshold = \App\Models\SystemSetting::get('manager_only_threshold', 2000);
        $ceoThreshold = \App\Models\SystemSetting::get('ceo_approval_threshold', 5000);

        $workflowInfo = [
            'is_sequential' => false,
            'current_step' => 1,
            'total_steps' => 1,
            'steps' => [],
            'next_approver' => null,
            'waiting_for' => null
        ];

        // Auto-approval: no additional approvals needed
        if ($amount <= $autoApprovalThreshold) {
            $workflowInfo['steps'] = [
                ['role' => 'Auto-Approval', 'status' => 'completed', 'description' => 'Request auto-approved based on amount threshold']
            ];
            return $workflowInfo;
        }

        // Manager-only approval
        if ($amount <= $managerOnlyThreshold) {
            $manager = $this->getDepartmentManager($employee->department_id);
            $hasManagerApproval = $this->hasManagerApproval($request);

            $workflowInfo['steps'] = [
                [
                    'role' => 'Manager',
                    'status' => $hasManagerApproval ? 'completed' : 'pending',
                    'description' => 'Manager approval required',
                    'approver' => $manager ? $manager->full_name : 'No manager assigned'
                ]
            ];

            if (!$hasManagerApproval) {
                $workflowInfo['waiting_for'] = 'Manager';
                $workflowInfo['next_approver'] = $manager ? $manager->full_name : 'No manager assigned';
            }

            return $workflowInfo;
        }

        // Manager + CEO approval
        $manager = $this->getDepartmentManager($employee->department_id);
        $admin = $this->getAdmin();
        $hasManagerApproval = $this->hasManagerApproval($request);
        $hasAdminApproval = $this->hasAdminApproval($request);

        $workflowInfo['is_sequential'] = true;
        $workflowInfo['total_steps'] = 2;

        $steps = [
            [
                'role' => 'Manager',
                'status' => $hasManagerApproval ? 'completed' : 'pending',
                'description' => 'Manager approval required',
                'approver' => $manager ? $manager->full_name : 'No manager assigned'
            ]
        ];

        if ($amount >= $ceoThreshold) {
            $steps[] = [
                'role' => 'Admin/CEO',
                'status' => $hasAdminApproval ? 'completed' : ($hasManagerApproval ? 'pending' : 'waiting'),
                'description' => 'CEO approval required for high-value request',
                'approver' => $admin ? $admin->full_name : 'No admin assigned'
            ];
            $workflowInfo['total_steps'] = 2;
        }

        $workflowInfo['steps'] = $steps;

        // Calculate current step based on completed approvals
        $completedSteps = 0;
        if ($hasManagerApproval) $completedSteps++;
        if ($amount >= $ceoThreshold && $hasAdminApproval) $completedSteps++;

        // Show the actual completed steps, not the next step
        $workflowInfo['current_step'] = $completedSteps;

        // Determine waiting status
        if (!$hasManagerApproval) {
            $workflowInfo['waiting_for'] = 'Manager';
            $workflowInfo['next_approver'] = $manager ? $manager->full_name : 'No manager assigned';
        } elseif ($amount >= $ceoThreshold && !$hasAdminApproval) {
            $workflowInfo['waiting_for'] = 'Admin/CEO';
            $workflowInfo['next_approver'] = $admin ? $admin->full_name : 'No admin assigned';
        }

        return $workflowInfo;
    }

    /**
     * Get dynamic workflow information
     */
    private function getDynamicWorkflowInfo(RequestModel $request, $rules): array
    {
        $employee = $request->employee;
        $workflowInfo = [
            'is_sequential' => $rules->count() > 1,
            'current_step' => 1,
            'total_steps' => $rules->count(),
            'steps' => [],
            'next_approver' => null,
            'waiting_for' => null
        ];

        $steps = [];
        $completedSteps = 0;
        $waitingFor = null;
        $nextApprover = null;

        foreach ($rules as $index => $rule) {
            $approver = $this->getApproverByRole($rule->approver_role, $employee->department_id);
            $hasApproval = $this->hasApprovalFromRole($request, $rule->approver_role, $employee->department_id);

            $stepStatus = 'waiting';
            if ($hasApproval) {
                $stepStatus = 'completed';
                $completedSteps++;
            } elseif ($index === 0 || $this->hasPreviousApprovals($request, $rules, $index)) {
                $stepStatus = 'pending';
                if (!$waitingFor) {
                    $waitingFor = $rule->approver_role;
                    $nextApprover = $approver ? $approver->full_name : 'No approver assigned';
                }
            }

            $steps[] = [
                'role' => $rule->approver_role,
                'status' => $stepStatus,
                'description' => "Approval required from {$rule->approver_role}",
                'approver' => $approver ? $approver->full_name : 'No approver assigned',
                'order' => $rule->order
            ];
        }

        $workflowInfo['steps'] = $steps;

        // Show the actual completed steps, not the next step
        $workflowInfo['current_step'] = $completedSteps;

        $workflowInfo['waiting_for'] = $waitingFor;
        $workflowInfo['next_approver'] = $nextApprover;

        return $workflowInfo;
    }

    /**
     * Check if previous approvals exist for dynamic workflow
     */
    private function hasPreviousApprovals(RequestModel $request, $rules, $currentIndex): bool
    {
        for ($i = 0; $i < $currentIndex; $i++) {
            $rule = $rules[$i];
            if (!$this->hasApprovalFromRole($request, $rule->approver_role, $request->employee->department_id)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Helper methods
     */
    private function getDepartmentManager(int $departmentId): ?\App\Models\User
    {
        return \App\Models\User::where('department_id', $departmentId)
            ->whereHas('role', function($query) {
                $query->where('name', 'manager');
            })
            ->first();
    }

    private function getAdmin(): ?\App\Models\User
    {
        return \App\Models\User::whereHas('role', function($query) {
            $query->where('name', 'admin');
        })->first();
    }

    private function getApproverByRole(string $role, int $departmentId): ?\App\Models\User
    {
        if ($role === 'Manager' || $role === 'manager') {
            return \App\Models\User::whereHas('role', function($query) {
                $query->where('name', 'manager');
            })
            ->where('department_id', $departmentId)
            ->first();
        } elseif ($role === 'Admin' || $role === 'admin') {
            return \App\Models\User::whereHas('role', function($query) {
                $query->where('name', 'admin');
            })->first();
        }
        return null;
    }

    private function hasManagerApproval(RequestModel $request): bool
    {
        return \App\Models\AuditLog::where('request_id', $request->id)
            ->where('action', 'Approved')
            ->whereHas('user', function($query) use ($request) {
                $query->whereHas('role', function($roleQuery) {
                    $roleQuery->where('name', 'manager');
                })
                ->where('department_id', $request->employee->department_id);
            })
            ->exists();
    }

    private function hasAdminApproval(RequestModel $request): bool
    {
        return \App\Models\AuditLog::where('request_id', $request->id)
            ->where('action', 'Approved')
            ->whereHas('user', function($query) {
                $query->whereHas('role', function($roleQuery) {
                    $roleQuery->where('name', 'admin');
                });
            })
            ->exists();
    }

    private function hasApprovalFromRole(RequestModel $request, string $role, int $departmentId): bool
    {
        $query = \App\Models\AuditLog::where('request_id', $request->id)
            ->where('action', 'Approved')
            ->whereHas('user', function($query) use ($role, $departmentId) {
                $query->whereHas('role', function($roleQuery) use ($role) {
                    if ($role === 'Manager' || $role === 'manager') {
                        $roleQuery->where('name', 'manager');
                    } elseif ($role === 'Admin' || $role === 'admin') {
                        $roleQuery->where('name', 'admin');
                    } else {
                        $roleQuery->where('name', $role);
                    }
                });

                if ($role === 'Manager' || $role === 'manager') {
                    $query->where('department_id', $departmentId);
                }
            });

        return $query->exists();
    }

    /**
     * Check if user can view a request
     */
    private function canViewRequest(RequestModel $request, $user): bool
    {
        switch ($user->role->name) {
            case 'employee':
                return $request->employee_id === $user->id;
            case 'manager':
                return $request->employee->department_id === $user->department_id;
            case 'admin':
                return true;
            case 'procurement':
                // Procurement can view their own requests (any status) OR all approved requests
                if ($request->employee_id === $user->id) {
                    return true;
                }
                return in_array($request->status, ['Approved', 'Pending Procurement', 'Ordered', 'Delivered', 'Cancelled']);
            default:
                return false;
        }
    }
}
