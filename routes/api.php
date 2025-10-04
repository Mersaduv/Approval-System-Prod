<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Api\RequestController;
use App\Http\Controllers\Api\DepartmentController;
use App\Http\Controllers\Api\Admin\ApprovalRuleController;
use App\Http\Controllers\Api\Admin\DepartmentController as AdminDepartmentController;
use App\Http\Controllers\Api\Admin\RoleController;
use App\Http\Controllers\Api\Admin\UserController;
use App\Http\Controllers\Api\Admin\SystemSettingsController;
use App\Http\Controllers\Api\WorkflowStepController;
use App\Http\Controllers\Api\DelegationController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\LeaveRequestController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware(['web', 'auth'])->get('/user', function (Request $request) {
    $user = $request->user();
    if ($user) {
        $user->load('role');
    }
    return response()->json([
        'success' => true,
        'data' => $user
    ]);
});

// CSRF token endpoint
Route::middleware(['web', 'auth'])->get('/csrf-token', function () {
    return response()->json([
        'success' => true,
        'csrf_token' => csrf_token()
    ]);
});

// Request routes
Route::middleware(['web', 'auth'])->group(function () {
    // Request management
    Route::get('/requests', [RequestController::class, 'index']);
    Route::post('/requests', [RequestController::class, 'store']);
    Route::delete('/requests/bulk-delete', [RequestController::class, 'bulkDelete']);
    Route::post('/requests/bulk-delete', [RequestController::class, 'bulkDelete']);
    Route::get('/requests/{id}', [RequestController::class, 'show']);
    Route::get('/requests/{id}/audit-logs', [RequestController::class, 'auditLogs']);

    // Dashboard stats
    Route::get('/dashboard/stats', [RequestController::class, 'dashboardStats']);


    // Request actions
    Route::post('/requests/{id}/approve', [RequestController::class, 'approve']);
    Route::post('/requests/{id}/reject', [RequestController::class, 'reject']);
    Route::post('/requests/{id}/delay', [RequestController::class, 'delay']);
    Route::post('/requests/{id}/bill-printing', [RequestController::class, 'billPrinting']);
    Route::post('/requests/{id}/finance-approve-with-bill', [RequestController::class, 'financeApproveWithBill']);
    Route::post('/requests/{id}/procurement', [RequestController::class, 'updateProcurement']);
    Route::post('/requests/{id}/process-procurement', [RequestController::class, 'processProcurement']);
    Route::post('/requests/{id}/rollback', [RequestController::class, 'rollbackRequest']);

    // Pending approvals
    Route::get('/requests/pending/approvals', [RequestController::class, 'pendingApprovals']);
    Route::get('/requests/pending/procurement', [RequestController::class, 'pendingProcurement']);
    Route::get('/requests/approved', [RequestController::class, 'approvedRequests']);

    // Procurement verification
    Route::get('/requests/pending/verification', [RequestController::class, 'pendingProcurementVerification']);
    Route::post('/requests/{id}/verify', [RequestController::class, 'processProcurementVerification']);


    // Delegation routes
    Route::get('/delegations', [DelegationController::class, 'index']);
    Route::get('/delegations/my', [DelegationController::class, 'myDelegations']);
    Route::get('/delegations/received', [DelegationController::class, 'receivedDelegations']);
    Route::post('/delegations', [DelegationController::class, 'store']);
    Route::put('/delegations/{id}', [DelegationController::class, 'update']);
    Route::delete('/delegations/{id}', [DelegationController::class, 'destroy']);
    Route::post('/delegations/{id}/reject', [DelegationController::class, 'reject']);
    Route::get('/delegations/available-users', [DelegationController::class, 'getAvailableUsers']);
    Route::get('/delegations/workflow-steps', [DelegationController::class, 'getWorkflowSteps']);
    Route::get('/delegations/stats', [DelegationController::class, 'getStats']);

    // Profile routes
    Route::post('/validate-current-password', [ProfileController::class, 'validateCurrentPassword']);

    // Leave Request routes
    Route::get('/leave-requests', [LeaveRequestController::class, 'index']);
    Route::post('/leave-requests', [LeaveRequestController::class, 'store']);
    Route::post('/leave-requests/bulk-delete', [LeaveRequestController::class, 'bulkDelete']);
    Route::get('/leave-requests/common-reasons', [LeaveRequestController::class, 'getCommonReasons']);
    Route::get('/leave-requests/{id}', [LeaveRequestController::class, 'show']);
    Route::post('/leave-requests/{id}/approve', [LeaveRequestController::class, 'approve']);
    Route::post('/leave-requests/{id}/reject', [LeaveRequestController::class, 'reject']);

    // Debug routes
    Route::get('/test-session', function () {
        $user = Auth::user();
        if ($user) {
            $user->load('role');
        }
        return response()->json([
            'success' => Auth::check(),
            'authenticated' => Auth::check(),
            'user' => $user,
            'session_id' => request()->session()->getId()
        ]);
    });
});

// Public API routes (authenticated users)
Route::middleware(['web', 'auth'])->group(function () {
    // Get departments by role - available to all authenticated users
    Route::get('/departments/by-role', [DepartmentController::class, 'getByRole']);
});

// Admin routes
Route::middleware(['web', 'auth'])->prefix('admin')->group(function () {
    // Department management
    Route::get('/departments', [AdminDepartmentController::class, 'index']);
    Route::post('/departments', [AdminDepartmentController::class, 'store']);
    Route::get('/departments/{id}', [AdminDepartmentController::class, 'show']);
    Route::put('/departments/{id}', [AdminDepartmentController::class, 'update']);
    Route::delete('/departments/{id}', [AdminDepartmentController::class, 'destroy']);
    Route::get('/departments/stats/overview', [AdminDepartmentController::class, 'getStats']);

    // Role management
    Route::get('/roles', [RoleController::class, 'index']);

    // User management
    Route::get('/users', [UserController::class, 'index']);
    Route::post('/users', [UserController::class, 'store']);
    Route::get('/users/roles/available', [UserController::class, 'getRoles']);
    Route::get('/users/permissions/available', [UserController::class, 'getPermissions']);
    Route::get('/users/stats/overview', [UserController::class, 'getStats']);

    // User trash management (must be before /users/{id} routes)
    Route::get('/users/trash', [UserController::class, 'trash']);
    Route::post('/users/{id}/restore', [UserController::class, 'restore']);
    Route::delete('/users/{id}/force', [UserController::class, 'forceDelete']);

    // User individual routes (must be after specific routes)
    Route::get('/users/{id}', [UserController::class, 'show']);
    Route::put('/users/{id}', [UserController::class, 'update']);
    Route::delete('/users/{id}', [UserController::class, 'destroy']);
    Route::put('/users/{id}/permissions', [UserController::class, 'updatePermissions']);


    // Approval rules management
    Route::get('/approval-rules', [ApprovalRuleController::class, 'index']);
    Route::post('/approval-rules', [ApprovalRuleController::class, 'store']);
    Route::get('/approval-rules/{id}', [ApprovalRuleController::class, 'show']);
    Route::put('/approval-rules/{id}', [ApprovalRuleController::class, 'update']);
    Route::delete('/approval-rules/{id}', [ApprovalRuleController::class, 'destroy']);
    Route::get('/approval-rules/roles/available', [ApprovalRuleController::class, 'getApproverRoles']);
    Route::get('/approval-rules/department/{departmentId}', [ApprovalRuleController::class, 'getDepartmentRules']);
    Route::put('/approval-rules/department/{departmentId}/bulk', [ApprovalRuleController::class, 'bulkUpdate']);
    Route::delete('/approval-rules/department/{departmentId}/bulk', [ApprovalRuleController::class, 'bulkDelete']);

    // System settings management
    Route::get('/settings', [SystemSettingsController::class, 'index']);
    Route::get('/settings/{key}', [SystemSettingsController::class, 'show']);
    Route::put('/settings', [SystemSettingsController::class, 'update']);
    Route::put('/settings/{key}', [SystemSettingsController::class, 'updateSetting']);
    Route::post('/settings/reset', [SystemSettingsController::class, 'reset']);

    // Workflow steps management
    Route::get('/workflow-steps', [WorkflowStepController::class, 'index']);
    Route::post('/workflow-steps', [WorkflowStepController::class, 'store']);
    Route::get('/workflow-steps/stats/overview', [WorkflowStepController::class, 'getStats']);
    Route::get('/workflow-steps/summary', [WorkflowStepController::class, 'getSummary']);
    Route::get('/workflow-steps/assignable-entities', [WorkflowStepController::class, 'getAssignableEntities']);
    Route::post('/workflow-steps/reorder', [WorkflowStepController::class, 'reorder']);
    Route::get('/workflow-steps/{id}', [WorkflowStepController::class, 'show']);
    Route::put('/workflow-steps/{id}', [WorkflowStepController::class, 'update']);
    Route::delete('/workflow-steps/{id}', [WorkflowStepController::class, 'destroy']);
    Route::post('/workflow-steps/{id}/duplicate', [WorkflowStepController::class, 'duplicate']);
});

