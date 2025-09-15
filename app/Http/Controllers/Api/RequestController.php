<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\WorkflowService;
use App\Models\Request as RequestModel;
use App\Models\User;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
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
        $query = RequestModel::with(['employee', 'employee.department', 'procurement', 'notifications', 'verifiedBy']);

        // Filter based on user role
        switch ($user->role->name) {
            case 'employee':
                $query->where('employee_id', $user->id);
                break;
            case 'manager':
                // Managers can see requests from their department OR requests assigned to their department in workflow steps OR requests assigned to them personally
                $query->where(function($q) use ($user) {
                    // Show requests from their own department
                    $q->whereHas('employee', function($empQuery) use ($user) {
                        $empQuery->where('department_id', $user->department_id);
                    })
                    // OR show requests assigned to their department in workflow steps
                    ->orWhere(function($workflowQuery) use ($user) {
                        $workflowQuery->whereIn('status', ['Pending Approval', 'Approved', 'Pending Procurement', 'Ordered', 'Delivered'])
                            ->whereExists(function($subQuery) use ($user) {
                                $subQuery->select(DB::raw(1))
                                    ->from('workflow_steps')
                                    ->join('workflow_step_assignments', 'workflow_steps.id', '=', 'workflow_step_assignments.workflow_step_id')
                                    ->where('workflow_step_assignments.assignable_type', 'App\\Models\\Department')
                                    ->where('workflow_step_assignments.assignable_id', $user->department_id)
                                    ->where('workflow_steps.is_active', true);
                            });
                    })
                    // OR show requests assigned to them personally in workflow steps
                    ->orWhere(function($personalQuery) use ($user) {
                        $personalQuery->whereIn('status', ['Pending Approval', 'Approved', 'Pending Procurement', 'Ordered', 'Delivered'])
                            ->whereExists(function($subQuery) use ($user) {
                                $subQuery->select(DB::raw(1))
                                    ->from('workflow_steps')
                                    ->join('workflow_step_assignments', 'workflow_steps.id', '=', 'workflow_step_assignments.workflow_step_id')
                                    ->where('workflow_step_assignments.assignable_type', 'App\\Models\\User')
                                    ->where('workflow_step_assignments.assignable_id', $user->id)
                                    ->where('workflow_steps.is_active', true);
                            });
                    });
                });
                break;
            case 'procurement':
                // Procurement users can see their own requests OR requests assigned to them in workflow steps
                $query->where(function($q) use ($user) {
                    // Show their own requests
                    $q->where('employee_id', $user->id)
                    // OR show requests assigned to them in workflow steps
                    ->orWhere(function($workflowQuery) use ($user) {
                        $workflowQuery->whereIn('status', [
                            'Pending Procurement Verification',
                            'Pending Approval',
                            'Approved',
                            'Pending Procurement',
                            'Ordered',
                            'Delivered',
                            'Cancelled'
                        ])
                        ->whereExists(function($subQuery) use ($user) {
                            $subQuery->select(DB::raw(1))
                                ->from('workflow_steps')
                                ->join('workflow_step_assignments', 'workflow_steps.id', '=', 'workflow_step_assignments.workflow_step_id')
                                ->where('workflow_step_assignments.assignable_type', 'App\\Models\\User')
                                ->where('workflow_step_assignments.assignable_id', $user->id)
                                ->where('workflow_steps.is_active', true);
                        });
                    })
                    ->orWhere(function($departmentQuery) use ($user) {
                        $departmentQuery->whereIn('status', [
                            'Pending Procurement Verification',
                            'Pending Approval',
                            'Approved',
                            'Pending Procurement',
                            'Ordered',
                            'Delivered',
                            'Cancelled'
                        ])
                        ->whereExists(function($subQuery) use ($user) {
                            $subQuery->select(DB::raw(1))
                                ->from('workflow_steps')
                                ->join('workflow_step_assignments', 'workflow_steps.id', '=', 'workflow_step_assignments.workflow_step_id')
                                ->where('workflow_step_assignments.assignable_type', 'App\\Models\\Department')
                                ->where('workflow_step_assignments.assignable_id', $user->department_id)
                                ->where('workflow_steps.is_active', true);
                        });
                    })
                    ->orWhere(function($roleQuery) use ($user) {
                        $roleQuery->whereIn('status', [
                            'Pending Procurement Verification',
                            'Pending Approval',
                            'Approved',
                            'Pending Procurement',
                            'Ordered',
                            'Delivered',
                            'Cancelled'
                        ])
                        ->whereExists(function($subQuery) use ($user) {
                            $subQuery->select(DB::raw(1))
                                ->from('workflow_steps')
                                ->join('workflow_step_assignments', 'workflow_steps.id', '=', 'workflow_step_assignments.workflow_step_id')
                                ->where('workflow_step_assignments.assignable_type', 'App\\Models\\Role')
                                ->where('workflow_step_assignments.assignable_id', $user->role_id)
                                ->where('workflow_steps.is_active', true);
                        });
                    });
                });
                break;
            case 'admin':
                // Can see all requests
                break;
            default:
                // For other roles (like Finance), check if they are assigned to any workflow steps
                if ($this->isUserAssignedToAnyWorkflowStep($user)) {
                    $query->where(function($q) use ($user) {
                        // Show requests that are assigned to this user's department in workflow steps
                        $q->whereHas('employee', function($empQuery) use ($user) {
                            $empQuery->where('department_id', $user->department_id);
                        })
                        ->orWhere(function($workflowQuery) use ($user) {
                            // Show requests that are in workflow steps assigned to this user's department
                            $workflowQuery->whereIn('status', ['Pending Approval', 'Approved', 'Pending Procurement', 'Ordered', 'Delivered'])
                                ->whereExists(function($subQuery) use ($user) {
                                    $subQuery->select(DB::raw(1))
                                        ->from('workflow_steps')
                                        ->join('workflow_step_assignments', 'workflow_steps.id', '=', 'workflow_step_assignments.workflow_step_id')
                                        ->where('workflow_step_assignments.assignable_type', 'App\\\\Models\\\\Department')
                                        ->where('workflow_step_assignments.assignable_id', $user->department_id)
                                        ->where('workflow_steps.is_active', true);
                                });
                        });
                    });
                } else {
                    // If user is not assigned to any workflow step, only show their own requests
                    $query->where('employee_id', $user->id);
                }
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
            'auditLogs.user',
            'verifiedBy'
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
     * Get procurement requests (all requests that need procurement processing)
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

            // Get all requests that are in procurement workflow
            // This includes: Pending Procurement Verification, Pending Approval, Approved, Pending Procurement, Ordered, Delivered, Cancelled
            $query = RequestModel::whereIn('status', [
                'Pending Procurement Verification',
                'Pending Approval',
                'Approved',
                'Pending Procurement',
                'Ordered',
                'Delivered',
                'Cancelled'
            ])
            ->with(['employee.department', 'procurement', 'verifiedBy']);

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
     * Get requests pending procurement verification
     */
    public function pendingProcurementVerification(): JsonResponse
    {
        try {
            // Check if user can process procurement verification
            /** @var \App\Models\User $user */
            $user = Auth::user();
            if (!$user || !$user->canProcessProcurement()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Only procurement team members can view verification requests'
                ], 403);
            }

            // Get all requests pending procurement verification
            $requests = RequestModel::where('procurement_status', 'Pending Verification')
                ->with(['employee.department', 'verifiedBy'])
                ->orderBy('created_at', 'asc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $requests
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch verification requests',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Process procurement verification
     */
    public function processProcurementVerification(Request $request, string $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'status' => 'required|in:Verified,Not Available,Rejected',
            'final_price' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string|max:1000'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Check if user can process procurement verification
        /** @var \App\Models\User $user */
        $user = Auth::user();
        if (!$user || !$user->canProcessProcurement()) {
            return response()->json([
                'success' => false,
                'message' => 'Only procurement team members can process verification requests'
            ], 403);
        }

        try {
            $this->workflowService->processProcurementVerification(
                $id,
                Auth::id(),
                $request->input('status'),
                $request->input('final_price'),
                $request->input('notes')
            );

            return response()->json([
                'success' => true,
                'message' => 'Procurement verification processed successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to process procurement verification',
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

        // Check if request is rejected
        if ($request->status === 'Rejected') {
            return $this->getRejectedWorkflowInfo($request);
        }

        // Special handling for procurement users - all steps are auto-approved
        if ($employee->isProcurement()) {
            return $this->getProcurementWorkflowInfo($request);
        }

        // Use dynamic workflow steps from WorkflowStep model
        $workflowSteps = \App\Models\WorkflowStep::getStepsForRequest($request);

        if ($workflowSteps->isEmpty()) {
            // Fallback to old system if no workflow steps are configured
            return $this->getLegacyWorkflowInfo($request);
        }

        return $this->getDynamicWorkflowStepsInfo($request, $workflowSteps);
    }

    /**
     * Get workflow info using dynamic workflow steps
     */
    private function getDynamicWorkflowStepsInfo(RequestModel $request, $workflowSteps): array
    {
        $steps = [];
        $currentStep = 0;
        $totalSteps = $workflowSteps->count();
        $waitingFor = null;
        $nextApprover = null;

        foreach ($workflowSteps as $index => $step) {
            $stepStatus = $this->getStepStatus($request, $step, $index);
            $stepInfo = [
                'id' => $step->id,
                'name' => $step->name,
                'role' => $this->getStepRoleName($step),
                'status' => $stepStatus,
                'description' => $step->description ?: $this->getStepDescription($step, $stepStatus),
                'approver' => $this->getStepApprover($step),
                'order' => $step->order_index,
                'step_type' => $step->step_type,
                'is_active' => $step->is_active,
                'timeout_hours' => $step->timeout_hours,
                'assignments' => $step->assignments->map(function($assignment) {
                    return [
                        'id' => $assignment->id,
                        'assignment_type' => $this->getAssignmentType($assignment),
                        'assignable_name' => $this->getAssignableName($assignment),
                        'is_required' => $assignment->is_required,
                        'priority' => $assignment->priority
                    ];
                })
            ];

            $steps[] = $stepInfo;

            // Determine current step and waiting status
            if ($stepStatus === 'completed') {
                $currentStep = $index + 1;
            } elseif ($stepStatus === 'pending' && !$waitingFor) {
                $waitingFor = $stepInfo['role'];
                $nextApprover = $stepInfo['approver'];
                // Don't update currentStep here - it should remain 0 until completed
            } elseif ($stepStatus === 'waiting' && !$waitingFor) {
                // If no pending step found, the next waiting step is the current one
                $waitingFor = $stepInfo['role'];
                $nextApprover = $stepInfo['approver'];
                // Don't update currentStep here - it should remain 0 until completed
            }
        }

        // Check if current user can approve this request
        $canApprove = $this->canApproveRequest($request, Auth::user());

        return [
            'is_sequential' => true,
            'current_step' => $currentStep,
            'total_steps' => $totalSteps,
            'steps' => $steps,
            'next_approver' => $nextApprover,
            'waiting_for' => $waitingFor,
            'workflow_type' => 'dynamic',
            'can_approve' => $canApprove,
            'can_approve_message' => $canApprove ? 'Waiting for your turn to approve' : 'Waiting for ' . ($waitingFor ?? 'approval')
        ];
    }

    /**
     * Get step status based on request and step
     */
    private function getStepStatus(RequestModel $request, $step, $index): string
    {
        // Check if step is rejected
        if ($this->isStepRejected($request, $step)) {
            return 'rejected';
        }

        // Check if step is completed
        if ($this->isStepCompleted($request, $step)) {
            return 'completed';
        }

        // Check if step is currently pending
        if ($this->isStepPending($request, $step, $index)) {
            return 'pending';
        }

        // For steps that are not yet active, show as waiting
        return 'waiting';
    }

    /**
     * Check if step is completed
     */
    private function isStepCompleted(RequestModel $request, $step): bool
    {
        // Implementation depends on your business logic
        // This is a simplified version
        switch ($step->step_type) {
            case 'approval':
                return $this->isApprovalStepCompleted($request, $step);
            case 'verification':
                return $this->isVerificationStepCompleted($request, $step);
            case 'notification':
                return $this->isNotificationStepCompleted($request, $step);
            default:
                return false;
        }
    }

    /**
     * Check if step is rejected
     */
    private function isStepRejected(RequestModel $request, $step): bool
    {
        // Check if request is rejected
        if ($request->status === 'Rejected') {
            return true;
        }

        // Check if step is specifically rejected
        return AuditLog::where('request_id', $request->id)
            ->where('action', 'Step Rejected')
            ->where('notes', 'like', '%' . $step->name . '%')
            ->exists();
    }

    /**
     * Check if step is currently pending
     */
    private function isStepPending(RequestModel $request, $step, $index): bool
    {
        // If step is already completed or rejected, it's not pending
        if ($this->isStepCompleted($request, $step) || $this->isStepRejected($request, $step)) {
            return false;
        }

        // Check if all previous steps are completed
        $previousStepsCompleted = true;
        // We need to get the actual workflow steps to check previous ones
        $workflowSteps = \App\Models\WorkflowStep::getStepsForRequest($request);

        for ($i = 0; $i < $index; $i++) {
            $previousStep = $workflowSteps[$i] ?? null;
            if ($previousStep && !$this->isStepCompleted($request, $previousStep)) {
                $previousStepsCompleted = false;
                break;
            }
        }

        return $previousStepsCompleted;
    }

    /**
     * Get step role name for display
     */
    private function getStepRoleName($step): string
    {
        return $step->name;
    }

    /**
     * Get step description
     */
    private function getStepDescription($step, $status): string
    {
        if ($step->description) {
            return $step->description;
        }

        switch ($step->step_type) {
            case 'approval':
                return 'Approval required';
            case 'verification':
                return 'Verification required';
            case 'notification':
                return 'Notification sent';
            default:
                return 'Step ' . ($step->order_index + 1);
        }
    }

    /**
     * Get step approver name
     */
    private function getStepApprover($step): string
    {
        $assignments = $step->assignments;
        if ($assignments->isEmpty()) {
            return 'Not assigned';
        }

        $assignableNames = $assignments->map(function($assignment) {
            return $this->getAssignableName($assignment);
        })->toArray();

        return implode(', ', $assignableNames);
    }

    /**
     * Get assignment type from assignable relationship
     */
    private function getAssignmentType($assignment): string
    {
        switch ($assignment->assignable_type) {
            case 'App\\Models\\User':
                return 'user';
            case 'App\\Models\\Role':
                $role = \App\Models\Role::find($assignment->assignable_id);
                return $role ? $role->name : 'admin';
            case 'App\\Models\\Department':
                $department = \App\Models\Department::find($assignment->assignable_id);
                return $department ? $department->name : 'department';
            default:
                return 'unknown';
        }
    }

    /**
     * Get assignable name for display
     */
    private function getAssignableName($assignment): string
    {
        switch ($assignment->assignable_type) {
            case 'App\\Models\\User':
                $user = \App\Models\User::find($assignment->assignable_id);
                return $user ? $user->full_name : 'Unknown User';
            case 'App\\Models\\Role':
                $role = \App\Models\Role::find($assignment->assignable_id);
                return $role ? $role->name : 'Unknown Role';
            case 'App\\Models\\Department':
                $department = \App\Models\Department::find($assignment->assignable_id);
                return $department ? $department->name : 'Unknown Department';
            default:
                return 'Unknown';
        }
    }

    /**
     * Check if approval step is completed
     */
    private function isApprovalStepCompleted(RequestModel $request, $step): bool
    {
        // Check if step is specifically completed in audit logs
        $stepName = strtolower($step->name);

        // Check for step completion in audit logs
        $stepCompleted = AuditLog::where('request_id', $request->id)
            ->where('action', 'Step completed')
            ->where('notes', 'like', '%' . $step->name . '%')
            ->exists();

        if ($stepCompleted) {
            return true;
        }

        // For approval steps, also check if user has approved
        if ($step->step_type === 'approval') {
            // Check if manager has approved (for Manager Approval step)
            if (strpos($stepName, 'manager') !== false) {
                $managerApproved = AuditLog::where('request_id', $request->id)
                    ->where('action', 'Approved')
                    ->whereHas('user', function($query) {
                        $query->whereHas('role', function($q) {
                            $q->where('name', 'manager');
                        });
                    })
                    ->exists();

                if ($managerApproved) {
                    return true;
                }
            }

            // Check if admin has approved (for CEO/Admin Approval step)
            if (strpos($stepName, 'ceo') !== false || strpos($stepName, 'admin') !== false) {
                $adminApproved = AuditLog::where('request_id', $request->id)
                    ->where('action', 'Approved')
                    ->whereHas('user', function($query) {
                        $query->whereHas('role', function($q) {
                            $q->where('name', 'admin');
                        });
                    })
                    ->exists();

                if ($adminApproved) {
                    return true;
                }
            }

            // Check if finance has approved (for Finance Approval step)
            if (strpos($stepName, 'finance') !== false) {
                $financeApproved = AuditLog::where('request_id', $request->id)
                    ->where('action', 'Approved')
                    ->whereHas('user', function($query) {
                        $query->whereHas('role', function($q) {
                            $q->where('name', 'manager');
                        })->where('department_id', 3); // Finance department
                    })
                    ->exists();

                if ($financeApproved) {
                    return true;
                }
            }
        }

        // For Procurement Processing step, check if request is in procurement phase
        if (strpos($stepName, 'procurement processing') !== false) {
            return in_array($request->status, ['Pending Procurement', 'Ordered', 'Delivered']);
        }

        // For other approval steps, check if request is approved or beyond
        if (in_array($request->status, ['Approved', 'Pending Procurement', 'Ordered', 'Delivered'])) {
            // Manager approval is completed when status is Approved or beyond
            if (strpos($stepName, 'manager') !== false) {
                return true;
            }

            // CEO/Admin approval is completed when status is Approved or beyond
            if (strpos($stepName, 'ceo') !== false || strpos($stepName, 'admin') !== false) {
                return true;
            }

            // Finance approval is completed when status is Approved or beyond
            if (strpos($stepName, 'finance') !== false) {
                return true;
            }

            // Auto-approval is completed when status is Approved or beyond
            if (strpos($stepName, 'auto') !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if verification step is completed
     */
    private function isVerificationStepCompleted(RequestModel $request, $step): bool
    {
        // Check if procurement verification is completed
        return $request->procurement_status === 'Verified';
    }

    /**
     * Check if notification step is completed
     */
    private function isNotificationStepCompleted(RequestModel $request, $step): bool
    {
        // Notifications are considered completed when sent
        return true; // This might need more complex logic based on your requirements
    }

    /**
     * Fallback to legacy workflow system
     */
    private function getLegacyWorkflowInfo(RequestModel $request): array
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
            'waiting_for' => null,
            'workflow_type' => 'legacy'
        ];

        if ($rules->isEmpty()) {
            // Use default workflow
            $workflowInfo = $this->getDefaultWorkflowInfo($request);
        } else {
            // Use dynamic rules
            $workflowInfo = $this->getDynamicWorkflowInfo($request, $rules);
        }

        // Add Procurement Verification step if needed
        $workflowInfo = $this->addProcurementVerificationStep($request, $workflowInfo);

        return $workflowInfo;
    }

    /**
     * Add Procurement Verification step to workflow
     */
    private function addProcurementVerificationStep(RequestModel $request, array $workflowInfo): array
    {
        // Only add procurement verification step for non-procurement users
        if ($request->employee->isProcurement()) {
            return $workflowInfo;
        }

        // Check if procurement verification is needed
        $needsProcurementVerification = in_array($request->status, [
            'Pending Procurement Verification',
            'Pending Approval',
            'Approved',
            'Pending Procurement',
            'Ordered',
            'Delivered',
            'Cancelled'
        ]);

        if (!$needsProcurementVerification) {
            return $workflowInfo;
        }

        // Determine procurement verification status
        $procurementStatus = 'waiting';
        if ($request->procurement_status === 'Verified') {
            $procurementStatus = 'completed';
        } elseif ($request->procurement_status === 'Not Available' || $request->procurement_status === 'Rejected') {
            $procurementStatus = 'rejected';
        } elseif ($request->status === 'Pending Procurement Verification') {
            $procurementStatus = 'pending';
        }

        // Add procurement verification step at the beginning
        $procurementStep = [
            'role' => 'Procurement',
            'status' => $procurementStatus,
            'description' => 'Procurement verification required',
            'approver' => 'Procurement Team',
            'order' => 0
        ];

        // Insert at the beginning of steps
        array_unshift($workflowInfo['steps'], $procurementStep);

        // Update total steps
        $workflowInfo['total_steps'] = count($workflowInfo['steps']);

        // Update current step and waiting status
        if ($procurementStatus === 'pending') {
            $workflowInfo['waiting_for'] = 'Procurement';
            $workflowInfo['next_approver'] = 'Procurement Team';
            $workflowInfo['current_step'] = 0;
        } elseif ($procurementStatus === 'completed') {
            // Move to next step if procurement is completed
            $workflowInfo['current_step'] = 1;
            // Find next pending step (skip the procurement step at index 0)
            foreach ($workflowInfo['steps'] as $index => $step) {
                if ($step['status'] === 'pending' && $index > 0) {
                    $workflowInfo['waiting_for'] = $step['role'];
                    $workflowInfo['next_approver'] = $step['approver'];
                    $workflowInfo['current_step'] = $index;
                    break;
                }
            }
        } elseif ($procurementStatus === 'rejected') {
            $workflowInfo['waiting_for'] = null;
            $workflowInfo['next_approver'] = null;
            $workflowInfo['current_step'] = 0;
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
     * Get procurement workflow information - only Admin/CEO approval required
     */
    private function getProcurementWorkflowInfo(RequestModel $request): array
    {
        $employee = $request->employee;
        $amount = $request->amount;

        // Get thresholds from settings
        $autoApprovalThreshold = \App\Models\SystemSetting::get('auto_approval_threshold', 1000);

        // Check if Admin approval is needed
        $needsAdminApproval = $amount > $autoApprovalThreshold;
        $hasAdminApproval = $this->hasAdminApproval($request);

        // Get Admin user for display
        $admin = $this->getApproverByRole('Admin', 0);

        if (!$needsAdminApproval) {
            // Auto-approved - no steps needed
            $steps = [];
            $currentStep = 0;
            $waitingFor = null;
        } else {
            // Admin approval required
            $stepStatus = $hasAdminApproval ? 'completed' : 'pending';
            $steps = [
                [
                    'role' => 'Admin',
                    'status' => $stepStatus,
                    'description' => 'Admin/CEO approval required for procurement request',
                    'approver' => $admin ? $admin->full_name : 'No admin assigned',
                    'order' => 1
                ]
            ];
            $currentStep = $hasAdminApproval ? 1 : 0;
            $waitingFor = $hasAdminApproval ? null : 'Admin';
        }

        return [
            'is_sequential' => false,
            'current_step' => $currentStep,
            'total_steps' => $needsAdminApproval ? 1 : 0,
            'steps' => $steps,
            'next_approver' => $needsAdminApproval && !$hasAdminApproval ? ($admin ? $admin->full_name : 'No admin assigned') : null,
            'waiting_for' => $waitingFor
        ];
    }

    /**
     * Get rejected workflow information - shows rejection status
     */
    private function getRejectedWorkflowInfo(RequestModel $request): array
    {
        // Get the rejection details from audit logs
        $rejectionLog = \App\Models\AuditLog::where('request_id', $request->id)
            ->where('action', 'Rejected')
            ->with('user')
            ->first();

        $rejectedBy = $rejectionLog ? $rejectionLog->user->full_name : 'Unknown';
        $rejectionReason = $rejectionLog ? $rejectionLog->notes : 'No reason provided';

        // Show the workflow steps that were in progress when rejected
        $employee = $request->employee;
        $department = $employee->department;
        $amount = $request->amount;

        // Get approval rules for this department
        $rules = \App\Models\ApprovalRule::where('department_id', $department->id)
            ->where('min_amount', '<=', $amount)
            ->where('max_amount', '>=', $amount)
            ->orderBy('order')
            ->get();

        $steps = [];
        $completedSteps = 0;

        if ($rules->isEmpty()) {
            // Use default workflow steps
            $autoApprovalThreshold = \App\Models\SystemSetting::get('auto_approval_threshold', 1000);
            $managerOnlyThreshold = \App\Models\SystemSetting::get('manager_only_threshold', 2000);
            $ceoThreshold = \App\Models\SystemSetting::get('ceo_approval_threshold', 5000);

            if ($amount > $autoApprovalThreshold) {
                if ($amount <= $managerOnlyThreshold) {
                    // Manager approval only - show as rejected since request was rejected
                    $steps[] = [
                        'role' => 'Manager',
                        'status' => 'rejected',
                        'description' => 'Manager approval rejected',
                        'approver' => $rejectedBy,
                        'order' => 1
                    ];
                    $completedSteps = 0;
                } else {
                    // Manager + CEO approval - show as rejected since request was rejected
                    $steps[] = [
                        'role' => 'Manager',
                        'status' => 'rejected',
                        'description' => 'Manager approval rejected',
                        'approver' => $rejectedBy,
                        'order' => 1
                    ];

                    $steps[] = [
                        'role' => 'Admin',
                        'status' => 'rejected',
                        'description' => 'Admin/CEO approval rejected',
                        'approver' => $rejectedBy,
                        'order' => 2
                    ];

                    $completedSteps = 0;
                }
            }
        } else {
            // Use dynamic rules - show all as rejected since request was rejected
            foreach ($rules as $index => $rule) {
                $steps[] = [
                    'role' => $rule->approver_role,
                    'status' => 'rejected',
                    'description' => "{$rule->approver_role} approval rejected",
                    'approver' => $rejectedBy,
                    'order' => $rule->order
                ];
            }
            $completedSteps = 0;
        }

        return [
            'is_sequential' => $rules->count() > 1,
            'current_step' => $completedSteps,
            'total_steps' => count($steps),
            'steps' => $steps,
            'next_approver' => null,
            'waiting_for' => null,
            'rejection_info' => [
                'rejected_by' => $rejectedBy,
                'rejection_reason' => $rejectionReason,
                'rejected_at' => $rejectionLog ? $rejectionLog->created_at : null
            ]
        ];
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
                // Managers can view requests from their department OR requests assigned to them in workflow steps
                if ($request->employee->department_id === $user->department_id) {
                    return true;
                }
                // Check if request is assigned to them personally in workflow steps
                if (in_array($request->status, ['Pending Approval', 'Approved', 'Pending Procurement', 'Ordered', 'Delivered'])) {
                    return $this->isRequestAssignedToUserPersonally($request, $user) ||
                           $this->isRequestAssignedToUserDepartment($request, $user);
                }
                return false;
            case 'admin':
                return true;
            case 'procurement':
                // Procurement can view their own requests (any status) OR all procurement-related requests
                if ($request->employee_id === $user->id) {
                    return true;
                }
                // Check if request is assigned to procurement in workflow steps
                if (in_array($request->status, [
                    'Pending Procurement Verification',
                    'Pending Approval',
                    'Approved',
                    'Pending Procurement',
                    'Ordered',
                    'Delivered',
                    'Cancelled'
                ])) {
                    return $this->isRequestAssignedToProcurement($request, $user);
                }
                return false;
            default:
                // For other roles (like Finance), check if they are assigned to any workflow steps
                if ($this->isUserAssignedToAnyWorkflowStep($user)) {
                    // Finance users can view requests that are assigned to their department in workflow steps
                    return in_array($request->status, ['Pending Approval', 'Approved', 'Pending Procurement', 'Ordered', 'Delivered']) &&
                           $this->isRequestAssignedToUserDepartment($request, $user);
                }
                // If user is not assigned to any workflow step, only show their own requests
                return $request->employee_id === $user->id;
        }
    }

    /**
     * Check if user can approve the current request
     */
    private function canApproveRequest(RequestModel $request, $user): bool
    {
        // Get current workflow steps
        $workflowSteps = \App\Models\WorkflowStep::getStepsForRequest($request);

        foreach ($workflowSteps as $step) {
            $stepStatus = $this->getStepStatus($request, $step, 0); // We'll check each step individually

            // If this step is pending, check if user can approve it
            if ($stepStatus === 'pending') {
                if ($this->canUserApproveStep($step, $user)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Check if user can approve a specific step
     */
    private function canUserApproveStep($step, $user): bool
    {
        $assignments = $step->assignments;

        if ($assignments->isEmpty()) {
            return false;
        }

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
    private function isUserAssignedToStep($assignment, $user): bool
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
     * Check if step is assigned to user
     */
    private function isStepForUser($step, $user): bool
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
     * Check if user is assigned to any workflow step
     */
    private function isUserAssignedToAnyWorkflowStep($user): bool
    {
        return \App\Models\WorkflowStepAssignment::where(function($query) use ($user) {
            $query->where('assignable_type', 'App\\\\Models\\\\User')
                  ->where('assignable_id', $user->id)
                  ->orWhere(function($roleQuery) use ($user) {
                      $roleQuery->where('assignable_type', 'App\\\\Models\\\\Role')
                                ->where('assignable_id', $user->role_id);
                  })
                  ->orWhere(function($deptQuery) use ($user) {
                      $deptQuery->where('assignable_type', 'App\\\\Models\\\\Department')
                                ->where('assignable_id', $user->department_id);
                  });
        })->exists();
    }

    /**
     * Check if a request is assigned to user personally in workflow steps
     */
    private function isRequestAssignedToUserPersonally(RequestModel $request, $user): bool
    {
        return \App\Models\WorkflowStepAssignment::where('assignable_type', 'App\\Models\\User')
            ->where('assignable_id', $user->id)
            ->whereHas('workflowStep', function($query) {
                $query->where('is_active', true);
            })
            ->exists();
    }

    /**
     * Check if a request is assigned to procurement in workflow steps
     */
    private function isRequestAssignedToProcurement(RequestModel $request, $user): bool
    {
        // Check if user is personally assigned to any workflow step
        $personalAssignment = \App\Models\WorkflowStepAssignment::where('assignable_type', 'App\\Models\\User')
            ->where('assignable_id', $user->id)
            ->whereHas('workflowStep', function($query) {
                $query->where('is_active', true);
            })
            ->exists();

        if ($personalAssignment) {
            return true;
        }

        // Check if user's department is assigned to any workflow step
        $departmentAssignment = \App\Models\WorkflowStepAssignment::where('assignable_type', 'App\\Models\\Department')
            ->where('assignable_id', $user->department_id)
            ->whereHas('workflowStep', function($query) {
                $query->where('is_active', true);
            })
            ->exists();

        if ($departmentAssignment) {
            return true;
        }

        // Check if user's role is assigned to any workflow step
        $roleAssignment = \App\Models\WorkflowStepAssignment::where('assignable_type', 'App\\Models\\Role')
            ->where('assignable_id', $user->role_id)
            ->whereHas('workflowStep', function($query) {
                $query->where('is_active', true);
            })
            ->exists();

        return $roleAssignment;
    }

    /**
     * Check if a request is assigned to user's department in workflow steps
     */
    private function isRequestAssignedToUserDepartment(RequestModel $request, $user): bool
    {
        // Get all workflow steps assigned to user's department
        $assignments = \App\Models\WorkflowStepAssignment::where('assignable_type', 'App\\\\Models\\\\Department')
            ->where('assignable_id', $user->department_id)
            ->whereHas('workflowStep', function($query) {
                $query->where('is_active', true);
            })
            ->get();

        if ($assignments->isEmpty()) {
            return false;
        }

        // Check if any of the assigned steps are applicable to this request
        foreach ($assignments as $assignment) {
            $step = $assignment->workflowStep;
            if (!$step) {
                continue;
            }

            // Check if step conditions are met for this request
            if ($this->isStepApplicableToRequest($step, $request)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if a workflow step is applicable to a request
     */
    private function isStepApplicableToRequest($step, RequestModel $request): bool
    {
        // If no conditions, always applicable
        if (empty($step->conditions)) {
            return true;
        }

        // Check each condition
        foreach ($step->conditions as $condition) {
            $field = $condition['field'] ?? null;
            $operator = $condition['operator'] ?? null;
            $value = $condition['value'] ?? null;

            if (!$field || !$operator) {
                continue;
            }

            $requestValue = data_get($request, $field);

            switch ($operator) {
                case '>':
                    if (!($requestValue > $value)) {
                        return false;
                    }
                    break;
                case '>=':
                    if (!($requestValue >= $value)) {
                        return false;
                    }
                    break;
                case '<':
                    if (!($requestValue < $value)) {
                        return false;
                    }
                    break;
                case '<=':
                    if (!($requestValue <= $value)) {
                        return false;
                    }
                    break;
                case '=':
                case '==':
                    if (!($requestValue == $value)) {
                        return false;
                    }
                    break;
                case '!=':
                    if (!($requestValue != $value)) {
                        return false;
                    }
                    break;
                case 'in':
                    if (!in_array($requestValue, (array)$value)) {
                        return false;
                    }
                    break;
                case 'not_in':
                    if (in_array($requestValue, (array)$value)) {
                        return false;
                    }
                    break;
            }
        }

        return true;
    }
}
