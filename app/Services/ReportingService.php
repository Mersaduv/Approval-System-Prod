<?php

namespace App\Services;

use App\Models\Request as RequestModel;
use App\Models\AuditLog;
use App\Models\User;
use App\Models\Department;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ReportingService
{
    /**
     * Get dashboard statistics
     */
    public function getDashboardStats($startDate = null, $endDate = null)
    {
        $startDate = $startDate ? Carbon::parse($startDate) : Carbon::now()->startOfMonth();
        $endDate = $endDate ? Carbon::parse($endDate) : Carbon::now()->endOfMonth();

        return [
            'total_requests' => RequestModel::whereBetween('created_at', [$startDate, $endDate])->count(),
            'pending_requests' => RequestModel::where('status', 'Pending')->whereBetween('created_at', [$startDate, $endDate])->count(),
            'approved_requests' => RequestModel::where('status', 'Approved')->whereBetween('created_at', [$startDate, $endDate])->count(),
            'rejected_requests' => RequestModel::where('status', 'Rejected')->whereBetween('created_at', [$startDate, $endDate])->count(),
            'delivered_requests' => RequestModel::where('status', 'Delivered')->whereBetween('created_at', [$startDate, $endDate])->count(),
            'total_amount' => RequestModel::whereBetween('created_at', [$startDate, $endDate])->sum('amount'),
            'average_amount' => RequestModel::whereBetween('created_at', [$startDate, $endDate])->avg('amount'),
            'approval_rate' => $this->calculateApprovalRate($startDate, $endDate),
            'average_processing_time' => $this->calculateAverageProcessingTime($startDate, $endDate),
        ];
    }

    /**
     * Get requests by department
     */
    public function getRequestsByDepartment($startDate = null, $endDate = null)
    {
        $startDate = $startDate ? Carbon::parse($startDate) : Carbon::now()->startOfMonth();
        $endDate = $endDate ? Carbon::parse($endDate) : Carbon::now()->endOfMonth();

        return RequestModel::select('departments.name as department_name')
            ->selectRaw('COUNT(requests.id) as total_requests')
            ->selectRaw('SUM(requests.amount) as total_amount')
            ->selectRaw('AVG(requests.amount) as average_amount')
            ->selectRaw('SUM(CASE WHEN requests.status = "Approved" THEN 1 ELSE 0 END) as approved_count')
            ->selectRaw('SUM(CASE WHEN requests.status = "Rejected" THEN 1 ELSE 0 END) as rejected_count')
            ->selectRaw('SUM(CASE WHEN requests.status = "Pending" THEN 1 ELSE 0 END) as pending_count')
            ->join('users', 'requests.employee_id', '=', 'users.id')
            ->join('departments', 'users.department_id', '=', 'departments.id')
            ->whereBetween('requests.created_at', [$startDate, $endDate])
            ->groupBy('departments.id', 'departments.name')
            ->orderBy('total_requests', 'desc')
            ->get();
    }

    /**
     * Get requests by user
     */
    public function getRequestsByUser($startDate = null, $endDate = null, $limit = 10)
    {
        $startDate = $startDate ? Carbon::parse($startDate) : Carbon::now()->startOfMonth();
        $endDate = $endDate ? Carbon::parse($endDate) : Carbon::now()->endOfMonth();

        return RequestModel::select('users.full_name', 'users.email', 'departments.name as department_name')
            ->selectRaw('COUNT(requests.id) as total_requests')
            ->selectRaw('SUM(requests.amount) as total_amount')
            ->selectRaw('AVG(requests.amount) as average_amount')
            ->selectRaw('SUM(CASE WHEN requests.status = "Approved" THEN 1 ELSE 0 END) as approved_count')
            ->selectRaw('SUM(CASE WHEN requests.status = "Rejected" THEN 1 ELSE 0 END) as rejected_count')
            ->join('users', 'requests.employee_id', '=', 'users.id')
            ->join('departments', 'users.department_id', '=', 'departments.id')
            ->whereBetween('requests.created_at', [$startDate, $endDate])
            ->groupBy('users.id', 'users.full_name', 'users.email', 'departments.name')
            ->orderBy('total_requests', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Get approval workflow statistics
     */
    public function getApprovalWorkflowStats($startDate = null, $endDate = null)
    {
        $startDate = $startDate ? Carbon::parse($startDate) : Carbon::now()->startOfMonth();
        $endDate = $endDate ? Carbon::parse($endDate) : Carbon::now()->endOfMonth();

        return [
            'total_approvals' => AuditLog::where('action', 'Approved')
                ->whereBetween('created_at', [$startDate, $endDate])
                ->count(),
            'total_rejections' => AuditLog::where('action', 'Rejected')
                ->whereBetween('created_at', [$startDate, $endDate])
                ->count(),
            'approvals_by_role' => $this->getApprovalsByRole($startDate, $endDate),
            'average_approval_time' => $this->calculateAverageApprovalTime($startDate, $endDate),
            'most_active_approvers' => $this->getMostActiveApprovers($startDate, $endDate),
        ];
    }

    /**
     * Get audit trail for a specific request
     */
    public function getRequestAuditTrail($requestId)
    {
        return AuditLog::where('request_id', $requestId)
            ->with(['user.department'])
            ->orderBy('created_at', 'asc')
            ->get();
    }

    /**
     * Get system activity log
     */
    public function getSystemActivityLog($startDate = null, $endDate = null, $limit = 100)
    {
        $startDate = $startDate ? Carbon::parse($startDate) : Carbon::now()->subDays(30);
        $endDate = $endDate ? Carbon::parse($endDate) : Carbon::now();

        return AuditLog::with(['user.department', 'request'])
            ->whereBetween('created_at', [$startDate, $endDate])
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Get monthly trends
     */
    public function getMonthlyTrends($months = 12)
    {
        $startDate = Carbon::now()->subMonths($months)->startOfMonth();
        $endDate = Carbon::now()->endOfMonth();

        return RequestModel::select(
                DB::raw('DATE_FORMAT(created_at, "%Y-%m") as month'),
                DB::raw('COUNT(*) as total_requests'),
                DB::raw('SUM(amount) as total_amount'),
                DB::raw('AVG(amount) as average_amount'),
                DB::raw('SUM(CASE WHEN status = "Approved" THEN 1 ELSE 0 END) as approved_count'),
                DB::raw('SUM(CASE WHEN status = "Rejected" THEN 1 ELSE 0 END) as rejected_count')
            )
            ->whereBetween('created_at', [$startDate, $endDate])
            ->groupBy('month')
            ->orderBy('month')
            ->get();
    }

    /**
     * Get performance metrics
     */
    public function getPerformanceMetrics($startDate = null, $endDate = null)
    {
        $startDate = $startDate ? Carbon::parse($startDate) : Carbon::now()->startOfMonth();
        $endDate = $endDate ? Carbon::parse($endDate) : Carbon::now()->endOfMonth();

        return [
            'approval_rate' => $this->calculateApprovalRate($startDate, $endDate),
            'average_processing_time' => $this->calculateAverageProcessingTime($startDate, $endDate),
            'average_approval_time' => $this->calculateAverageApprovalTime($startDate, $endDate),
            'requests_per_day' => $this->calculateRequestsPerDay($startDate, $endDate),
            'top_requested_items' => $this->getTopRequestedItems($startDate, $endDate),
        ];
    }

    /**
     * Calculate approval rate
     */
    private function calculateApprovalRate($startDate, $endDate)
    {
        $total = RequestModel::whereBetween('created_at', [$startDate, $endDate])
            ->whereIn('status', ['Approved', 'Rejected'])
            ->count();

        if ($total === 0) return 0;

        $approved = RequestModel::whereBetween('created_at', [$startDate, $endDate])
            ->where('status', 'Approved')
            ->count();

        return round(($approved / $total) * 100, 2);
    }

    /**
     * Calculate average processing time
     */
    private function calculateAverageProcessingTime($startDate, $endDate)
    {
        $requests = RequestModel::whereBetween('created_at', [$startDate, $endDate])
            ->whereIn('status', ['Approved', 'Rejected', 'Delivered'])
            ->get();

        if ($requests->isEmpty()) return 0;

        $totalHours = $requests->sum(function ($request) {
            return $request->updated_at->diffInHours($request->created_at);
        });

        return round($totalHours / $requests->count(), 2);
    }

    /**
     * Calculate average approval time
     */
    private function calculateAverageApprovalTime($startDate, $endDate)
    {
        $approvals = AuditLog::where('action', 'Approved')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->with('request')
            ->get();

        if ($approvals->isEmpty()) return 0;

        $totalHours = $approvals->sum(function ($log) {
            return $log->created_at->diffInHours($log->request->created_at);
        });

        return round($totalHours / $approvals->count(), 2);
    }

    /**
     * Calculate requests per day
     */
    private function calculateRequestsPerDay($startDate, $endDate)
    {
        $totalDays = $startDate->diffInDays($endDate) + 1;
        $totalRequests = RequestModel::whereBetween('created_at', [$startDate, $endDate])->count();

        return round($totalRequests / $totalDays, 2);
    }

    /**
     * Get approvals by role
     */
    private function getApprovalsByRole($startDate, $endDate)
    {
        return AuditLog::select('users.role')
            ->selectRaw('COUNT(*) as approval_count')
            ->join('users', 'audit_logs.user_id', '=', 'users.id')
            ->where('audit_logs.action', 'Approved')
            ->whereBetween('audit_logs.created_at', [$startDate, $endDate])
            ->groupBy('users.role')
            ->orderBy('approval_count', 'desc')
            ->get();
    }

    /**
     * Get most active approvers
     */
    private function getMostActiveApprovers($startDate, $endDate, $limit = 5)
    {
        return AuditLog::select('users.full_name', 'users.role')
            ->selectRaw('COUNT(*) as approval_count')
            ->join('users', 'audit_logs.user_id', '=', 'users.id')
            ->where('audit_logs.action', 'Approved')
            ->whereBetween('audit_logs.created_at', [$startDate, $endDate])
            ->groupBy('users.id', 'users.full_name', 'users.role')
            ->orderBy('approval_count', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Get top requested items
     */
    private function getTopRequestedItems($startDate, $endDate, $limit = 10)
    {
        return RequestModel::select('item')
            ->selectRaw('COUNT(*) as request_count')
            ->selectRaw('SUM(amount) as total_amount')
            ->selectRaw('AVG(amount) as average_amount')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->groupBy('item')
            ->orderBy('request_count', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Export data to CSV
     */
    public function exportToCsv($data, $filename = 'export.csv')
    {
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        $callback = function() use ($data) {
            $file = fopen('php://output', 'w');

            if (!empty($data)) {
                // Write headers
                fputcsv($file, array_keys($data[0]));

                // Write data
                foreach ($data as $row) {
                    fputcsv($file, $row);
                }
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}
