<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LeaveRequest;
use App\Models\User;
use App\Models\AuditLog;
use App\Models\WorkflowStep;
use App\Services\WorkflowService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class LeaveRequestController extends Controller
{
    protected $workflowService;

    public function __construct(WorkflowService $workflowService)
    {
        $this->workflowService = $workflowService;
    }

    /**
     * Display a listing of leave requests
     */
    public function index(Request $request): JsonResponse
    {
        $user = Auth::user();
        $query = LeaveRequest::with(['employee', 'employee.department', 'employee.role', 'approvedBy', 'rejectedBy', 'auditLogs.user']);

        // Check if user wants to see all requests (for managers, admins, and HR users)
        $tab = $request->get('tab', 'my-requests');
        $showAllRequests = $tab === 'all-requests' && ($user->role->name === 'admin' || $user->role->name === 'manager' || $user->role->name === 'hr');

        // Filter based on user role
        switch ($user->role->name) {
            case 'employee':
                $query->where('employee_id', $user->id);
                break;
            case 'manager':
                // Check if manager is in HR department - they should see all leave requests assigned to them
                if ($user->department && strtolower($user->department->name) === 'hr') {
                    if (!$showAllRequests) {
                        // For 'my-requests' tab, show requests they need to approve
                        $query->whereIn('status', ['Pending Approval']);
                    }
                    // For 'all-requests' tab, show all requests (no additional filter)
                } else {
                    // Regular manager - show requests from their department
                    if ($showAllRequests) {
                        // Show all requests from their department
                        $query->whereHas('employee', function($empQuery) use ($user) {
                            $empQuery->where('department_id', $user->department_id);
                        });
                    } else {
                        // Show requests they need to approve from their department
                        $query->where(function($q) use ($user) {
                            $q->whereHas('employee', function($empQuery) use ($user) {
                                $empQuery->where('department_id', $user->department_id);
                            });
                        });
                    }
                }
                break;
            case 'admin':
            case 'hr':
                if (!$showAllRequests) {
                    // For 'my-requests' tab, show requests they need to approve
                    $query->whereIn('status', ['Pending Approval']);
                }
                // For 'all-requests' tab, show all requests (no additional filter)
                break;
            default:
                $query->where('employee_id', $user->id);
                break;
        }

        // Status filter
        if ($request->has('status') && $request->status !== 'all') {
            $status = $request->status;
            $query->where('status', $status);
        }

        // Date range filter
        if ($request->has('start_date') && $request->start_date) {
            $query->where('start_date', '>=', $request->start_date);
        }
        if ($request->has('end_date') && $request->end_date) {
            $query->where('end_date', '<=', $request->end_date);
        }

        // No leave type filter needed since we removed the field

        // Pagination
        $perPage = $request->get('per_page', 10);
        $currentPage = $request->get('page', 1);

        $leaveRequests = $query->orderBy('created_at', 'desc')->paginate($perPage, ['*'], 'page', $currentPage);

        return response()->json([
            'success' => true,
            'data' => $leaveRequests->items(),
            'pagination' => [
                'current_page' => $leaveRequests->currentPage(),
                'per_page' => $leaveRequests->perPage(),
                'total' => $leaveRequests->total(),
                'last_page' => $leaveRequests->lastPage(),
                'from' => $leaveRequests->firstItem(),
                'to' => $leaveRequests->lastItem(),
            ]
        ]);
    }

    /**
     * Store a newly created leave request
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'reason' => 'required|string|max:1000',
            'start_date' => 'required|date|after_or_equal:today',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            $user = Auth::user();
            $totalDays = LeaveRequest::calculateTotalDays($request->start_date, $request->end_date);

            $leaveRequest = LeaveRequest::create([
                'employee_id' => $user->id,
                'reason' => $request->reason,
                'start_date' => $request->start_date,
                'end_date' => $request->end_date,
                'total_days' => $totalDays,
                'status' => 'Pending',
            ]);

            // Log the creation
            $this->logAction(
                $user->id,
                $leaveRequest->id,
                'Leave Request Created',
                "Leave request created for {$totalDays} days ({$request->start_date} to {$request->end_date})"
            );

            // Start workflow process
            $this->workflowService->processLeaveRequestWorkflow($leaveRequest);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Leave request submitted successfully',
                'data' => $leaveRequest->load(['employee', 'employee.department'])
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to create leave request: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified leave request
     */
    public function show($id): JsonResponse
    {
        $leaveRequest = LeaveRequest::with([
            'employee',
            'employee.department',
            'employee.role',
            'approvedBy',
            'rejectedBy',
            'auditLogs.user'
        ])->findOrFail($id);

        $user = Auth::user();

        // Check if user can view this leave request
        if (!$this->canViewLeaveRequest($leaveRequest, $user)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to view this leave request'
            ], 403);
        }

        // Get workflow steps for this leave request
        $workflowSteps = $this->getLeaveWorkflowSteps($leaveRequest);

        return response()->json([
            'success' => true,
            'data' => [
                'leave_request' => $leaveRequest,
                'workflow_steps' => $workflowSteps,
                'can_approve' => $this->canApproveLeaveRequest($leaveRequest, $user),
                'can_reject' => $this->canRejectLeaveRequest($leaveRequest, $user),
            ]
        ]);
    }

    /**
     * Approve a leave request
     */
    public function approve(Request $request, $id): JsonResponse
    {
        $leaveRequest = LeaveRequest::findOrFail($id);
        $user = Auth::user();

        if (!$this->canApproveLeaveRequest($leaveRequest, $user)) {
            return response()->json([
                'success' => false,
                'message' => 'You are not authorized to approve this leave request'
            ], 403);
        }

        if (!$leaveRequest->canBeApproved()) {
            return response()->json([
                'success' => false,
                'message' => 'This leave request cannot be approved'
            ], 400);
        }

        $validator = Validator::make($request->all(), [
            'notes' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $success = $this->workflowService->approveLeaveRequest($leaveRequest->id, $user->id, $request->notes);

            if (!$success) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to approve leave request'
                ], 500);
            }

            return response()->json([
                'success' => true,
                'message' => 'Leave request approved successfully'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to approve leave request: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reject a leave request
     */
    public function reject(Request $request, $id): JsonResponse
    {
        $leaveRequest = LeaveRequest::findOrFail($id);
        $user = Auth::user();

        if (!$this->canRejectLeaveRequest($leaveRequest, $user)) {
            return response()->json([
                'success' => false,
                'message' => 'You are not authorized to reject this leave request'
            ], 403);
        }

        if (!$leaveRequest->canBeRejected()) {
            return response()->json([
                'success' => false,
                'message' => 'This leave request cannot be rejected'
            ], 400);
        }

        $validator = Validator::make($request->all(), [
            'reason' => 'required|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $success = $this->workflowService->rejectLeaveRequest($leaveRequest->id, $user->id, $request->reason);

            if (!$success) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to reject leave request'
                ], 500);
            }

            return response()->json([
                'success' => true,
                'message' => 'Leave request rejected successfully'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to reject leave request: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Cancel a leave request
     */
    public function cancel($id): JsonResponse
    {
        $leaveRequest = LeaveRequest::findOrFail($id);
        $user = Auth::user();

        if (!$this->canCancelLeaveRequest($leaveRequest, $user)) {
            return response()->json([
                'success' => false,
                'message' => 'You are not authorized to cancel this leave request'
            ], 403);
        }

        if (!$leaveRequest->canBeCancelled()) {
            return response()->json([
                'success' => false,
                'message' => 'This leave request cannot be cancelled'
            ], 400);
        }

        try {
            DB::beginTransaction();

            $leaveRequest->update([
                'status' => 'Cancelled',
            ]);

            // Log the cancellation
            $this->logAction(
                $user->id,
                $leaveRequest->id,
                'Leave Request Cancelled',
                "Cancelled by {$user->full_name}"
            );

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Leave request cancelled successfully'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to cancel leave request: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Bulk delete leave requests
     */
    public function bulkDelete(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'request_ids' => 'required|array|min:1',
            'request_ids.*' => 'required|integer|exists:leave_requests,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            $user = Auth::user();
            $requestIds = $request->request_ids;

            // Get the leave requests to be deleted
            $leaveRequests = LeaveRequest::whereIn('id', $requestIds)->get();

            // Check permissions for each request
            foreach ($leaveRequests as $leaveRequest) {
                // Admin can delete all requests, others can only delete their own
                if ($user->role->name !== 'admin' && $leaveRequest->employee_id !== $user->id) {
                    return response()->json([
                        'success' => false,
                        'message' => 'You are not authorized to delete some of these leave requests'
                    ], 403);
                }

                // Note: Removed status check - all leave requests can now be deleted regardless of status
            }

            // Delete related records first
            foreach ($leaveRequests as $leaveRequest) {
                // Delete related audit logs
                AuditLog::where('leave_request_id', $leaveRequest->id)->delete();

                // Delete related notifications
                \App\Models\Notification::where('leave_request_id', $leaveRequest->id)->delete();

                // Delete any workflow step assignments related to this leave request
                // (if any exist in the future)

                // Delete any approval tokens related to this leave request
                // Note: ApprovalToken table uses request_id for regular requests, not leave requests
                // Leave requests don't typically use approval tokens, so we skip this

                // Log the deletion before actually deleting the request
                $this->logAction(
                    $user->id,
                    $leaveRequest->id,
                    'Leave Request Deleted',
                    "Leave request #{$leaveRequest->id} (Status: {$leaveRequest->status}) deleted by {$user->full_name}"
                );
            }

            // Delete the leave requests
            LeaveRequest::whereIn('id', $requestIds)->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => count($requestIds) . ' leave request(s) deleted successfully'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete leave requests: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get common leave reasons
     */
    public function getCommonReasons(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => LeaveRequest::getCommonReasons()
        ]);
    }

    // Helper methods
    private function canViewLeaveRequest(LeaveRequest $leaveRequest, User $user): bool
    {
        if ($leaveRequest->employee_id === $user->id) return true;
        if (in_array($user->role->name, ['admin', 'hr'])) return true;

        // Check if manager is in HR department - they should see all leave requests
        if ($user->role->name === 'manager' && $user->department && strtolower($user->department->name) === 'hr') {
            return true;
        }

        // Regular manager can view requests from their department
        if ($user->role->name === 'manager' && $leaveRequest->employee->department_id === $user->department_id) return true;
        return false;
    }

    private function canApproveLeaveRequest(LeaveRequest $leaveRequest, User $user): bool
    {
        if (!$leaveRequest->canBeApproved()) return false;
        return $this->workflowService->canApproveLeaveRequest($leaveRequest, $user);
    }

    private function canRejectLeaveRequest(LeaveRequest $leaveRequest, User $user): bool
    {
        return $this->canApproveLeaveRequest($leaveRequest, $user);
    }

    private function canCancelLeaveRequest(LeaveRequest $leaveRequest, User $user): bool
    {
        if (!$leaveRequest->canBeCancelled()) return false;
        if ($leaveRequest->employee_id === $user->id) return true;
        if ($user->role->name === 'admin') return true;
        return false;
    }

    private function processLeaveWorkflow(LeaveRequest $leaveRequest): void
    {
        $employee = $leaveRequest->employee;
        if ($employee->role->name === 'employee') {
            $leaveRequest->update(['status' => 'Pending Approval']);
        }
    }

    private function getLeaveWorkflowSteps(LeaveRequest $leaveRequest): array
    {
        // Get dynamic workflow steps for leave requests
        $workflowSteps = WorkflowStep::where('step_category', 'leave')
            ->where('is_active', true)
            ->orderBy('order_index')
            ->get();

        if ($workflowSteps->isEmpty()) {
            // Fallback to simple workflow if no dynamic steps defined
            return [[
                'name' => 'Manager Approval',
                'status' => $leaveRequest->status === 'Approved' ? 'completed' :
                           ($leaveRequest->status === 'Rejected' ? 'rejected' : 'pending'),
                'completed_at' => $leaveRequest->approved_at ?? $leaveRequest->rejected_at,
                'completed_by' => $leaveRequest->approvedBy ?? $leaveRequest->rejectedBy,
                'notes' => $leaveRequest->manager_notes ?? $leaveRequest->rejection_reason,
            ]];
        }

        // Build dynamic workflow steps
        $steps = [];
        foreach ($workflowSteps as $step) {
            $stepStatus = $this->getLeaveStepStatus($leaveRequest, $step);
            $stepInfo = $this->getLeaveStepInfo($leaveRequest, $step, $stepStatus);

            $steps[] = [
                'id' => $step->id,
                'name' => $step->name,
                'description' => $step->description,
                'status' => $stepStatus,
                'completed_at' => $stepInfo['completed_at'],
                'completed_by' => $stepInfo['completed_by'],
                'notes' => $stepInfo['notes'],
                'step_type' => $step->step_type,
                'order_index' => $step->order_index,
            ];
        }

        return $steps;
    }

    /**
     * Get the status of a specific workflow step for a leave request
     */
    private function getLeaveStepStatus(LeaveRequest $leaveRequest, WorkflowStep $step): string
    {
        // If leave request is rejected, all steps are rejected
        if ($leaveRequest->status === 'Rejected') {
            return 'rejected';
        }

        // If leave request is cancelled, all steps are cancelled
        if ($leaveRequest->status === 'Cancelled') {
            return 'cancelled';
        }

        // Get all workflow steps for leave requests in order
        $allSteps = WorkflowStep::where('step_category', 'leave')
            ->where('is_active', true)
            ->orderBy('order_index')
            ->get();

        // Find the current step index
        $currentStepIndex = $allSteps->search(function ($s) use ($step) {
            return $s->id === $step->id;
        });

        if ($currentStepIndex === false) {
            return 'waiting';
        }

        // Check if this step is completed by looking at audit logs
        $stepCompleted = $leaveRequest->auditLogs()
            ->where('action', 'like', '%approved%')
            ->where('notes', 'like', '%' . $step->name . '%')
            ->exists();

        if ($stepCompleted) {
            return 'completed';
        }

        // If leave request is approved, check if this step should be completed
        if ($leaveRequest->status === 'Approved') {
            // Count completed steps before this one
            $completedStepsBefore = 0;
            for ($i = 0; $i < $currentStepIndex; $i++) {
                $checkStep = $allSteps[$i];
                $isCompleted = $leaveRequest->auditLogs()
                    ->where('action', 'like', '%approved%')
                    ->where('notes', 'like', '%' . $checkStep->name . '%')
                    ->exists();

                if ($isCompleted) {
                    $completedStepsBefore++;
                }
            }

            // If all previous steps are completed, this step should be completed too
            if ($completedStepsBefore === $currentStepIndex) {
                return 'completed';
            }
        }

        // Check if this step is currently active (pending)
        if ($leaveRequest->status === 'Pending Approval') {
            // Count completed steps before this one
            $completedStepsBefore = 0;
            for ($i = 0; $i < $currentStepIndex; $i++) {
                $checkStep = $allSteps[$i];
                $isCompleted = $leaveRequest->auditLogs()
                    ->where('action', 'like', '%approved%')
                    ->where('notes', 'like', '%' . $checkStep->name . '%')
                    ->exists();

                if ($isCompleted) {
                    $completedStepsBefore++;
                }
            }

            // If all previous steps are completed, this step is pending
            if ($completedStepsBefore === $currentStepIndex) {
                return 'pending';
            }
        }

        return 'waiting';
    }

    /**
     * Get additional info for a workflow step
     */
    private function getLeaveStepInfo(LeaveRequest $leaveRequest, WorkflowStep $step, string $status): array
    {
        $info = [
            'completed_at' => null,
            'completed_by' => null,
            'notes' => null,
        ];

        if ($status === 'completed' || $status === 'rejected') {
            // Find the audit log for this step
            $auditLog = $leaveRequest->auditLogs()
                ->with('user')
                ->where(function($query) use ($step) {
                    $query->where('action', 'like', '%approved%')
                          ->orWhere('action', 'like', '%rejected%');
                })
                ->where('notes', 'like', '%' . $step->name . '%')
                ->orderBy('created_at', 'desc')
                ->first();

            if ($auditLog) {
                $info['completed_at'] = $auditLog->created_at;
                $info['completed_by'] = $auditLog->user;
                $info['notes'] = $auditLog->notes;
            }
        }

        return $info;
    }

    private function logAction($userId, $leaveRequestId, $action, $notes = null, $oldValues = null, $newValues = null): void
    {
        AuditLog::create([
            'user_id' => $userId,
            'leave_request_id' => $leaveRequestId,
            'action' => $action,
            'notes' => $notes,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }
}
