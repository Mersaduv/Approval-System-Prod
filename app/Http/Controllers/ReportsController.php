<?php

namespace App\Http\Controllers;

use App\Services\ReportingService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Carbon\Carbon;

class ReportsController extends Controller
{
    protected $reportingService;

    public function __construct(ReportingService $reportingService)
    {
        $this->reportingService = $reportingService;
    }

    /**
     * Get dashboard statistics
     */
    public function dashboardStats(Request $request): JsonResponse
    {
        $startDate = $request->get('start_date');
        $endDate = $request->get('end_date');

        $stats = $this->reportingService->getDashboardStats($startDate, $endDate);

        return response()->json([
            'success' => true,
            'data' => $stats
        ]);
    }

    /**
     * Get requests by department report
     */
    public function requestsByDepartment(Request $request): JsonResponse
    {
        $startDate = $request->get('start_date');
        $endDate = $request->get('end_date');

        $data = $this->reportingService->getRequestsByDepartment($startDate, $endDate);

        return response()->json([
            'success' => true,
            'data' => $data
        ]);
    }

    /**
     * Get requests by user report
     */
    public function requestsByUser(Request $request): JsonResponse
    {
        $startDate = $request->get('start_date');
        $endDate = $request->get('end_date');
        $limit = $request->get('limit', 10);

        $data = $this->reportingService->getRequestsByUser($startDate, $endDate, $limit);

        return response()->json([
            'success' => true,
            'data' => $data
        ]);
    }

    /**
     * Get approval workflow statistics
     */
    public function approvalWorkflowStats(Request $request): JsonResponse
    {
        $startDate = $request->get('start_date');
        $endDate = $request->get('end_date');

        $data = $this->reportingService->getApprovalWorkflowStats($startDate, $endDate);

        return response()->json([
            'success' => true,
            'data' => $data
        ]);
    }

    /**
     * Get request audit trail
     */
    public function requestAuditTrail(Request $request, $requestId): JsonResponse
    {
        $data = $this->reportingService->getRequestAuditTrail($requestId);

        return response()->json([
            'success' => true,
            'data' => $data
        ]);
    }

    /**
     * Get system activity log
     */
    public function systemActivityLog(Request $request): JsonResponse
    {
        $startDate = $request->get('start_date');
        $endDate = $request->get('end_date');
        $limit = $request->get('limit', 100);

        $data = $this->reportingService->getSystemActivityLog($startDate, $endDate, $limit);

        return response()->json([
            'success' => true,
            'data' => $data
        ]);
    }

    /**
     * Get monthly trends
     */
    public function monthlyTrends(Request $request): JsonResponse
    {
        $months = $request->get('months', 12);

        $data = $this->reportingService->getMonthlyTrends($months);

        return response()->json([
            'success' => true,
            'data' => $data
        ]);
    }

    /**
     * Get performance metrics
     */
    public function performanceMetrics(Request $request): JsonResponse
    {
        $startDate = $request->get('start_date');
        $endDate = $request->get('end_date');

        $data = $this->reportingService->getPerformanceMetrics($startDate, $endDate);

        return response()->json([
            'success' => true,
            'data' => $data
        ]);
    }

    /**
     * Export requests to CSV
     */
    public function exportRequests(Request $request)
    {
        $startDate = $request->get('start_date');
        $endDate = $request->get('end_date');

        $requests = \App\Models\Request::with(['employee.department'])
            ->when($startDate, function($query, $startDate) {
                return $query->where('created_at', '>=', Carbon::parse($startDate));
            })
            ->when($endDate, function($query, $endDate) {
                return $query->where('created_at', '<=', Carbon::parse($endDate));
            })
            ->get()
            ->map(function($request) {
                return [
                    'ID' => $request->id,
                    'Item' => $request->item,
                    'Description' => $request->description,
                    'Amount' => $request->amount,
                    'Status' => $request->status,
                    'Employee' => $request->employee->full_name,
                    'Department' => $request->employee->department->name,
                    'Created At' => $request->created_at->format('Y-m-d H:i:s'),
                    'Updated At' => $request->updated_at->format('Y-m-d H:i:s'),
                ];
            })
            ->toArray();

        return $this->reportingService->exportToCsv($requests, 'requests_export.csv');
    }

    /**
     * Export audit log to CSV
     */
    public function exportAuditLog(Request $request)
    {
        $startDate = $request->get('start_date');
        $endDate = $request->get('end_date');

        $auditLogs = \App\Models\AuditLog::with(['user.department', 'request'])
            ->when($startDate, function($query, $startDate) {
                return $query->where('created_at', '>=', Carbon::parse($startDate));
            })
            ->when($endDate, function($query, $endDate) {
                return $query->where('created_at', '<=', Carbon::parse($endDate));
            })
            ->get()
            ->map(function($log) {
                return [
                    'ID' => $log->id,
                    'User' => $log->user->full_name,
                    'Role' => $log->user->role,
                    'Department' => $log->user->department->name,
                    'Action' => $log->action,
                    'Request ID' => $log->request_id,
                    'Request Item' => $log->request ? $log->request->item : 'N/A',
                    'Notes' => $log->notes,
                    'IP Address' => $log->ip_address,
                    'Created At' => $log->created_at->format('Y-m-d H:i:s'),
                ];
            })
            ->toArray();

        return $this->reportingService->exportToCsv($auditLogs, 'audit_log_export.csv');
    }
}
