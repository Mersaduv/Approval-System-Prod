<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\RequestController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\Admin\ApprovalRuleController;
use App\Http\Controllers\Api\Admin\DepartmentController;
use App\Http\Controllers\Api\Admin\UserController;
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

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// Request routes
Route::middleware('auth:sanctum')->group(function () {
    // Request management
    Route::get('/requests', [RequestController::class, 'index']);
    Route::post('/requests', [RequestController::class, 'store']);
    Route::get('/requests/{id}', [RequestController::class, 'show']);

    // Request actions
    Route::post('/requests/{id}/approve', [RequestController::class, 'approve']);
    Route::post('/requests/{id}/reject', [RequestController::class, 'reject']);
    Route::post('/requests/{id}/procurement', [RequestController::class, 'updateProcurement']);

    // Pending approvals
    Route::get('/requests/pending/approvals', [RequestController::class, 'pendingApprovals']);

    // Notification routes
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount']);
    Route::post('/notifications/{id}/read', [NotificationController::class, 'markAsRead']);
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllAsRead']);
    Route::delete('/notifications/{id}', [NotificationController::class, 'destroy']);
});

// Admin routes
Route::middleware('auth:sanctum')->prefix('admin')->group(function () {
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
});

// Reporting routes
Route::middleware('auth:sanctum')->prefix('reports')->group(function () {
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
