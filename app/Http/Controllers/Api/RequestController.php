<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\WorkflowService;
use App\Models\Request as RequestModel;
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
        if (!Auth::user()->canProcessProcurement()) {
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
    public function pendingProcurement(): JsonResponse
    {
        try {
            // Check if user can process procurement
            if (!Auth::user()->canProcessProcurement()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Only procurement team members can view procurement requests'
                ], 403);
            }

            // Get all approved requests that are in procurement workflow
            $requests = RequestModel::whereIn('status', ['Approved', 'Pending Procurement', 'Ordered', 'Delivered', 'Cancelled'])
                ->with(['employee.department', 'procurement'])
                ->orderBy('created_at', 'desc')
                ->get();

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
            default:
                return false;
        }
    }
}
