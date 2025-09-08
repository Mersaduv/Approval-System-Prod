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
        switch ($user->role) {
            case 'Employee':
                $query->where('employee_id', $user->id);
                break;
            case 'Manager':
                $query->whereHas('employee', function($q) use ($user) {
                    $q->where('department_id', $user->department_id);
                });
                break;
            case 'Procurement':
                $query->where('status', 'Approved');
                break;
            case 'Admin':
            case 'CEO':
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
     * Update procurement status
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

        // Check if user is procurement
        if (Auth::user()->role !== 'Procurement') {
            return response()->json([
                'success' => false,
                'message' => 'Only procurement team can update procurement status'
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
     * Get requests pending approval for current user
     */
    public function pendingApprovals(): JsonResponse
    {
        $user = Auth::user();

        $query = RequestModel::with(['employee', 'employee.department'])
            ->where('status', 'Pending');

        // Filter based on user role and department
        switch ($user->role) {
            case 'Manager':
                $query->whereHas('employee', function($q) use ($user) {
                    $q->where('department_id', $user->department_id);
                });
                break;
            case 'SalesManager':
                $query->where(function($q) {
                    $q->where('item', 'like', '%purchase%')
                      ->orWhere('item', 'like', '%buy%')
                      ->orWhere('item', 'like', '%order%')
                      ->orWhere('description', 'like', '%purchase%')
                      ->orWhere('description', 'like', '%buy%')
                      ->orWhere('description', 'like', '%order%');
                });
                break;
            case 'CEO':
                $query->where('amount', '>=', 5000);
                break;
            case 'Admin':
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
     * Check if user can view a request
     */
    private function canViewRequest(RequestModel $request, $user): bool
    {
        switch ($user->role) {
            case 'Employee':
                return $request->employee_id === $user->id;
            case 'Manager':
                return $request->employee->department_id === $user->department_id;
            case 'Procurement':
                return in_array($request->status, ['Approved', 'Delivered']);
            case 'Admin':
            case 'CEO':
                return true;
            default:
                return false;
        }
    }
}
