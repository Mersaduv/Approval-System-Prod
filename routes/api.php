<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\RequestController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\Admin\ApprovalRuleController;
use App\Http\Controllers\Api\Admin\DepartmentController;
use App\Http\Controllers\Api\Admin\UserController;
use App\Http\Controllers\Api\Admin\SystemSettingsController;
use App\Http\Controllers\Api\WorkflowStepController;
use App\Http\Controllers\ReportsController;

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
    return response()->json([
        'success' => true,
        'data' => $request->user()->load('role')
    ]);
});

// Request routes
Route::middleware(['web', 'auth'])->group(function () {
    // Request management
    Route::get('/requests', [RequestController::class, 'index']);
    Route::post('/requests', [RequestController::class, 'store']);
    Route::get('/requests/{id}', [RequestController::class, 'show']);
    Route::get('/requests/{id}/audit-logs', [RequestController::class, 'auditLogs']);

    // Request actions
    Route::post('/requests/{id}/approve', [RequestController::class, 'approve']);
    Route::post('/requests/{id}/reject', [RequestController::class, 'reject']);
    Route::post('/requests/{id}/procurement', [RequestController::class, 'updateProcurement']);
    Route::post('/requests/{id}/process-procurement', [RequestController::class, 'processProcurement']);
    Route::post('/requests/{id}/rollback', [RequestController::class, 'rollbackRequest']);

    // Pending approvals
    Route::get('/requests/pending/approvals', [RequestController::class, 'pendingApprovals']);
    Route::get('/requests/pending/procurement', [RequestController::class, 'pendingProcurement']);

    // Procurement verification
    Route::get('/requests/pending/verification', [RequestController::class, 'pendingProcurementVerification']);
    Route::post('/requests/{id}/verify', [RequestController::class, 'processProcurementVerification']);

    // Notification routes
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount']);
    Route::post('/notifications/{id}/read', [NotificationController::class, 'markAsRead']);
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllAsRead']);
    Route::delete('/notifications/{id}', [NotificationController::class, 'destroy']);

    // Debug routes
    Route::get('/test-session', function () {
        return response()->json([
            'success' => Auth::check(),
            'authenticated' => Auth::check(),
            'user' => Auth::user() ? Auth::user()->load('role') : null,
            'session_id' => request()->session()->getId()
        ]);
    });
});

// Admin routes
Route::middleware(['web', 'auth'])->prefix('admin')->group(function () {
    // Department management
    Route::get('/departments', [DepartmentController::class, 'index']);
    Route::post('/departments', [DepartmentController::class, 'store']);
    Route::get('/departments/{id}', [DepartmentController::class, 'show']);
    Route::put('/departments/{id}', [DepartmentController::class, 'update']);
    Route::delete('/departments/{id}', [DepartmentController::class, 'destroy']);
    Route::get('/departments/stats/overview', [DepartmentController::class, 'getStats']);

    // User management
    Route::get('/users', [UserController::class, 'index']);
    Route::post('/users', [UserController::class, 'store']);
    Route::get('/users/{id}', [UserController::class, 'show']);
    Route::put('/users/{id}', [UserController::class, 'update']);
    Route::delete('/users/{id}', [UserController::class, 'destroy']);
    Route::get('/users/roles/available', [UserController::class, 'getRoles']);
    Route::get('/users/permissions/available', [UserController::class, 'getPermissions']);
    Route::put('/users/{id}/permissions', [UserController::class, 'updatePermissions']);
    Route::get('/users/stats/overview', [UserController::class, 'getStats']);


    // Approval rules management
    Route::get('/approval-rules', [ApprovalRuleController::class, 'index']);
    Route::post('/approval-rules', [ApprovalRuleController::class, 'store']);
    Route::get('/approval-rules/{id}', [ApprovalRuleController::class, 'show']);
    Route::put('/approval-rules/{id}', [ApprovalRuleController::class, 'update']);
    Route::delete('/approval-rules/{id}', [ApprovalRuleController::class, 'destroy']);
    Route::get('/approval-rules/roles/available', [ApprovalRuleController::class, 'getApproverRoles']);
    Route::get('/approval-rules/department/{departmentId}', [ApprovalRuleController::class, 'getDepartmentRules']);
    Route::put('/approval-rules/department/{departmentId}/bulk', [ApprovalRuleController::class, 'bulkUpdate']);

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

// Reporting routes
Route::middleware(['web', 'auth'])->prefix('reports')->group(function () {
    Route::get('/dashboard-stats', [ReportsController::class, 'dashboardStats']);
    Route::get('/requests-by-department', [ReportsController::class, 'requestsByDepartment']);
    Route::get('/requests-by-user', [ReportsController::class, 'requestsByUser']);
    Route::get('/approval-workflow-stats', [ReportsController::class, 'approvalWorkflowStats']);
    Route::get('/request-audit-trail/{requestId}', [ReportsController::class, 'requestAuditTrail']);
    Route::get('/system-activity-log', [ReportsController::class, 'systemActivityLog']);
    Route::get('/monthly-trends', [ReportsController::class, 'monthlyTrends']);
    Route::get('/performance-metrics', [ReportsController::class, 'performanceMetrics']);
    Route::get('/export/requests', [ReportsController::class, 'exportRequests']);
    Route::get('/export/audit-log', [ReportsController::class, 'exportAuditLog']);
});
