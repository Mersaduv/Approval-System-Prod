import { Head, Link } from '@inertiajs/react'
import AppLayout from '../Layouts/AppLayout'
import { useState, useEffect } from 'react'
import { CardSkeleton } from '../Components/SkeletonLoader'
import axios from 'axios'

export default function Home({ auth }) {
    const [stats, setStats] = useState({
        totalRequests: 0,
        pendingRequests: 0,
        approvedRequests: 0,
        rejectedRequests: 0,
        myRequests: 0,
        pendingApprovals: 0
    })
    const [recentRequests, setRecentRequests] = useState([])
    const [recentLeaveRequests, setRecentLeaveRequests] = useState([])
    const [loading, setLoading] = useState(true)
    const [error, setError] = useState(null)
    const [activeTab, setActiveTab] = useState('regular')

    const fetchDashboardData = async () => {
        setLoading(true)
        setError(null)
        try {
            // Fetch regular requests
            const regularResponse = await axios.get('/api/requests?limit=5')
            if (regularResponse.data.success) {
                setRecentRequests(regularResponse.data.data || [])
            }

            // Fetch leave requests
            const leaveResponse = await axios.get('/api/leave-requests?limit=5')
            if (leaveResponse.data.success) {
                setRecentLeaveRequests(leaveResponse.data.data || [])
            }

            // Fetch dashboard stats
            const statsResponse = await axios.get('/api/dashboard/stats')
            if (statsResponse.data.success) {
                setStats(statsResponse.data.data.stats)
            }
        } catch (error) {
            console.error('Error fetching dashboard data:', error)
            setError('Error loading dashboard data. Please try again.')
            // Fallback to empty data on error
            setStats({
                totalRequests: 0,
                pendingRequests: 0,
                approvedRequests: 0,
                rejectedRequests: 0,
                myRequests: 0,
                pendingApprovals: 0
            })
            setRecentRequests([])
            setRecentLeaveRequests([])
        } finally {
            setLoading(false)
        }
    }

    useEffect(() => {
        fetchDashboardData()
    }, [])

    const isRequestDelayed = (request) => {
        if (!request || !request.audit_logs) return false;

        // Check if there's a "Delayed" action in audit logs
        const hasDelayedAction = request.audit_logs.some(log => log.action === "Delayed");

        if (!hasDelayedAction) return false;

        // Check if Finance Approval step has been completed after the delay
        // If Finance Approval step is approved, rejected, or completed, the request is no longer delayed
        const financeApprovalCompleted = request.audit_logs.some(log =>
            (log.action === "Step completed" || log.action === "Approved" || log.action === "Rejected") &&
            log.notes &&
            log.notes.includes("Finance Approval")
        );

        // If Finance Approval step is completed, the request is no longer delayed
        return !financeApprovalCompleted;
    };

    // Check if the request creator is soft-deleted (in trash)
    const isRequestCreatorInTrash = (request) => {
        return request?.employee?.deleted_at !== null && request?.employee?.deleted_at !== undefined;
    };

    const getStatusColor = (status, request = null) => {
        // Check if request creator is in trash - show as disabled
        if (request && isRequestCreatorInTrash(request)) {
            return 'bg-red-100 text-red-800';
        }

        // Check if request is delayed
        if (request && isRequestDelayed(request)) {
            return 'bg-orange-100 text-orange-800';
        }

        switch (status.toLowerCase()) {
            case 'pending': return 'bg-yellow-100 text-yellow-800'
            case 'pending procurement verification': return 'bg-orange-100 text-orange-800'
            case 'approved': return 'bg-green-100 text-green-800'
            case 'rejected': return 'bg-red-100 text-red-800'
            case 'pending procurement': return 'bg-blue-100 text-blue-800'
            case 'ordered': return 'bg-purple-100 text-purple-800'
            case 'delivered': return 'bg-green-100 text-green-800'
            case 'cancelled': return 'bg-gray-100 text-gray-800'
            default: return 'bg-gray-100 text-gray-800'
        }
    }

    const formatDateWithDay = (dateString) => {
        if (!dateString) return '';

        try {
            const date = new Date(dateString);
            const options = {
                weekday: 'short',
                year: 'numeric',
                month: 'short',
                day: 'numeric'
            };
            return date.toLocaleDateString('en-US', options);
        } catch (error) {
            return dateString;
        }
    }

    const calculateDays = (startDate, endDate) => {
        if (!startDate || !endDate) return 0;

        try {
            const start = new Date(startDate);
            const end = new Date(endDate);
            const diffTime = Math.abs(end - start);
            const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24)) + 1; // +1 to include both start and end dates
            return diffDays;
        } catch (error) {
            return 0;
        }
    }

    const getStatusDisplayText = (status, request = null) => {
        // Check if request creator is in trash - show as disabled
        if (request && isRequestCreatorInTrash(request)) {
            return "Disabled";
        }

        // Check if request is delayed
        if (request && isRequestDelayed(request)) {
            return "Delayed (Waiting for finance Approval)";
        }

        if (status === "Pending Procurement Verification") {
            return "Pending Procurement";
        }
        // If status is Approved but no procurement record exists, show as Pending Procurement
        if (status === "Approved" && request && !request.procurement) {
            return "Pending Procurement";
        }
        return status;
    }

    // Remove full page loading - we'll show skeleton loading instead

    return (
        <AppLayout title="Home" auth={auth}>
            <div className="space-y-6">
                {/* Header */}
                <div className="flex justify-between items-start">
                    <div>
                        <h1 className="text-2xl font-bold text-gray-900">Dashboard</h1>
                        <p className="text-gray-600 mt-1">Welcome back! Here's what's happening with your requests.</p>
                    </div>
                </div>

                {/* Error Message */}
                {error && (
                    <div className="bg-red-50 border border-red-200 rounded-lg p-4 mb-6">
                        <div className="flex">
                            <div className="flex-shrink-0">
                                <span className="text-red-400">⚠️</span>
                            </div>
                            <div className="ml-3">
                                <p className="text-sm text-red-800">{error}</p>
                            </div>
                        </div>
                    </div>
                )}

                {/* Stats Grid */}
                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-4 lg:gap-6 mb-8">
                    <div className="bg-white rounded-lg shadow-sm p-4 lg:p-6">
                        <div className="flex items-center">
                            <div className="p-2 lg:p-3 bg-blue-100 rounded-lg">
                                <span className="text-lg lg:text-2xl">📊</span>
                            </div>
                            <div className="ml-3 lg:ml-4">
                                <p className="text-xs lg:text-sm font-medium text-gray-600">Total Requests</p>
                                <p className="text-lg lg:text-2xl font-bold text-gray-900">{stats.totalRequests}</p>
                            </div>
                        </div>
                    </div>

                    <div className="bg-white rounded-lg shadow-sm p-4 lg:p-6">
                        <div className="flex items-center">
                            <div className="p-2 lg:p-3 bg-yellow-100 rounded-lg">
                                <span className="text-lg lg:text-2xl">⏳</span>
                            </div>
                            <div className="ml-3 lg:ml-4">
                                <p className="text-xs lg:text-sm font-medium text-gray-600">Pending Requests</p>
                                <p className="text-lg lg:text-2xl font-bold text-gray-900">{stats.pendingRequests}</p>
                            </div>
                        </div>
                    </div>

                    <div className="bg-white rounded-lg shadow-sm p-4 lg:p-6">
                        <div className="flex items-center">
                            <div className="p-2 lg:p-3 bg-green-100 rounded-lg">
                                <span className="text-lg lg:text-2xl">✅</span>
                            </div>
                            <div className="ml-3 lg:ml-4">
                                <p className="text-xs lg:text-sm font-medium text-gray-600">Approved Requests</p>
                                <p className="text-lg lg:text-2xl font-bold text-gray-900">{stats.approvedRequests}</p>
                            </div>
                        </div>
                    </div>

                    <div className="bg-white rounded-lg shadow-sm p-4 lg:p-6">
                        <div className="flex items-center">
                            <div className="p-2 lg:p-3 bg-red-100 rounded-lg">
                                <span className="text-lg lg:text-2xl">❌</span>
                            </div>
                            <div className="ml-3 lg:ml-4">
                                <p className="text-xs lg:text-sm font-medium text-gray-600">Rejected Requests</p>
                                <p className="text-lg lg:text-2xl font-bold text-gray-900">{stats.rejectedRequests}</p>
                            </div>
                        </div>
                    </div>

                    <div className="bg-white rounded-lg shadow-sm p-4 lg:p-6">
                        <div className="flex items-center">
                            <div className="p-2 lg:p-3 bg-purple-100 rounded-lg">
                                <span className="text-lg lg:text-2xl">📝</span>
                            </div>
                            <div className="ml-3 lg:ml-4">
                                <p className="text-xs lg:text-sm font-medium text-gray-600">My Requests</p>
                                <p className="text-lg lg:text-2xl font-bold text-gray-900">{stats.myRequests}</p>
                            </div>
                        </div>
                    </div>

                    <div className="bg-white rounded-lg shadow-sm p-4 lg:p-6">
                        <div className="flex items-center">
                            <div className="p-2 lg:p-3 bg-orange-100 rounded-lg">
                                <span className="text-lg lg:text-2xl">🔔</span>
                            </div>
                            <div className="ml-3 lg:ml-4">
                                <p className="text-xs lg:text-sm font-medium text-gray-600">Pending Approvals</p>
                                <p className="text-lg lg:text-2xl font-bold text-gray-900">{stats.pendingApprovals}</p>
                            </div>
                        </div>
                    </div>
                </div>

                {/* Quick Actions */}
                <div className="bg-white rounded-lg shadow-sm p-4 lg:p-6 mb-8">
                    <h2 className="text-lg lg:text-xl font-semibold text-gray-900 mb-4">Quick Actions</h2>
                    <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 lg:gap-4">
                        {/* New Request - Available for all users */}
                        <Link
                            href="/requests/new"
                            className="flex items-center p-3 lg:p-4 border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors"
                        >
                            <div className="p-2 bg-blue-100 rounded-lg mr-3">
                                <span className="text-lg lg:text-xl">➕</span>
                            </div>
                            <div className="min-w-0 flex-1">
                                <p className="font-medium text-gray-900 text-sm lg:text-base">New Request</p>
                                <p className="text-xs lg:text-sm text-gray-500">Submit a new request</p>
                            </div>
                        </Link>

                        {/* View Requests - Available for all users */}
                        <Link
                            href="/requests"
                            className="flex items-center p-3 lg:p-4 border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors"
                        >
                            <div className="p-2 bg-green-100 rounded-lg mr-3">
                                <span className="text-lg lg:text-xl">📋</span>
                            </div>
                            <div className="min-w-0 flex-1">
                                <p className="font-medium text-gray-900 text-sm lg:text-base">View Requests</p>
                                <p className="text-xs lg:text-sm text-gray-500">Manage all requests</p>
                            </div>
                        </Link>

                        {/* Procurement Management - Only for procurement users */}
                        {auth.user?.role?.name === 'procurement' && (
                            <>
                                <Link
                                    href="/procurement/verification"
                                    className="flex items-center p-3 lg:p-4 border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors"
                                >
                                    <div className="p-2 bg-blue-100 rounded-lg mr-3">
                                        <span className="text-lg lg:text-xl">🔍</span>
                                    </div>
                                    <div className="min-w-0 flex-1">
                                        <p className="font-medium text-gray-900 text-sm lg:text-base">Verification</p>
                                        <p className="text-xs lg:text-sm text-gray-500">Verify market availability</p>
                                    </div>
                                </Link>
                                <Link
                                    href="/procurement"
                                    className="flex items-center p-3 lg:p-4 border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors"
                                >
                                    <div className="p-2 bg-orange-100 rounded-lg mr-3">
                                        <span className="text-lg lg:text-xl">📦</span>
                                    </div>
                                    <div className="min-w-0 flex-1">
                                        <p className="font-medium text-gray-900 text-sm lg:text-base">Procurement</p>
                                        <p className="text-xs lg:text-sm text-gray-500">Manage procurement requests</p>
                                    </div>
                                </Link>
                            </>
                        )}

                        {/* User Management - Only for admin users */}
                        {auth.user?.role?.name === 'admin' && (
                            <Link
                                href="/users"
                                className="flex items-center p-3 lg:p-4 border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors"
                            >
                                <div className="p-2 bg-indigo-100 rounded-lg mr-3">
                                    <span className="text-lg lg:text-xl">👥</span>
                                </div>
                                <div className="min-w-0 flex-1">
                                    <p className="font-medium text-gray-900 text-sm lg:text-base">User Management</p>
                                    <p className="text-xs lg:text-sm text-gray-500">Manage system users</p>
                                </div>
                            </Link>
                        )}

                        {/* Settings - Only for admin users */}
                        {auth.user?.role?.name === 'admin' && (
                            <Link
                                href="/settings"
                                className="flex items-center p-3 lg:p-4 border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors"
                            >
                                <div className="p-2 bg-gray-100 rounded-lg mr-3">
                                    <span className="text-lg lg:text-xl">⚙️</span>
                                </div>
                                <div className="min-w-0 flex-1">
                                    <p className="font-medium text-gray-900 text-sm lg:text-base">Settings</p>
                                    <p className="text-xs lg:text-sm text-gray-500">System configuration</p>
                                </div>
                            </Link>
                        )}

                    </div>
                </div>

                {/* Recent Requests */}
                <div className="grid grid-cols-1 lg:grid-cols-1 gap-6 lg:gap-8">
                    <div className="bg-white rounded-lg shadow-sm">
                        <div className="px-4 lg:px-6 py-4 border-b border-gray-200">
                            <div className="flex items-center justify-between">
                                <h3 className="text-base lg:text-lg font-medium text-gray-900">Recent Requests</h3>

                            </div>
                        </div>

                        {/* Request Type Tabs */}
                        <div className="px-4 lg:px-6 py-3 border-b border-gray-200">
                            <nav className="flex space-x-1 bg-gray-100 p-1 rounded-lg">
                                <button
                                    onClick={() => setActiveTab('regular')}
                                    className={`px-3 py-1 text-xs font-medium rounded-md transition-colors ${
                                        activeTab === 'regular'
                                            ? 'bg-white text-blue-600 shadow-sm'
                                            : 'text-gray-600 hover:text-gray-900'
                                    }`}
                                >
                                    Requests
                                </button>
                                <button
                                    onClick={() => setActiveTab('leave')}
                                    className={`px-3 py-1 text-xs font-medium rounded-md transition-colors ${
                                        activeTab === 'leave'
                                            ? 'bg-white text-blue-600 shadow-sm'
                                            : 'text-gray-600 hover:text-gray-900'
                                    }`}
                                >
                                    Leave Requests
                                </button>
                            </nav>
                        </div>

                        <div className="p-4 lg:p-6">
                            <div className="space-y-4">
                                {loading ? (
                                    <div className="space-y-3">
                                        {Array.from({ length: 3 }).map((_, index) => (
                                            <div key={index} className="flex items-center justify-between animate-pulse">
                                                <div className="flex-1 min-w-0">
                                                    <div className="h-4 bg-gray-200 rounded w-3/4 mb-2"></div>
                                                    <div className="h-3 bg-gray-200 rounded w-1/2"></div>
                                                </div>
                                                <div className="h-6 bg-gray-200 rounded-full w-20"></div>
                                            </div>
                                        ))}
                                    </div>
                                ) : (
                                    <>
                                        {/* Regular Requests Tab */}
                                        {activeTab === 'regular' && (
                                            <div className="space-y-3">
                                                {recentRequests.length > 0 ? (
                                                    recentRequests.map((request) => (
                                                        <div key={request.id} className="p-4 bg-gray-50 hover:bg-gray-100 rounded-lg border border-gray-200 hover:border-gray-300 transition-all duration-200">
                                                            <div className="flex items-center justify-between">
                                                                <div className="flex-1 min-w-0">
                                                                    <p className="text-sm font-semibold text-gray-900 truncate mb-1">
                                                                        {request.item}
                                                                    </p>
                                                                    <p className="text-sm text-gray-600">
                                                                        <span className="font-medium">{request.amount.toLocaleString()} AFN</span>
                                                                    </p>
                                                                    <p className="text-xs text-gray-500">
                                                                        {request.employee_name}
                                                                    </p>
                                                                </div>
                                                                <div className="flex items-center space-x-2 ml-4">
                                                                    <span className={`inline-flex items-center px-3 py-1 rounded-full text-xs font-medium ${getStatusColor(request.status, request)}`}>
                                                                        {getStatusDisplayText(request.status, request)}
                                                                    </span>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    ))
                                                ) : (
                                                    <div className="text-center py-8">
                                                        <div className="p-4 bg-gray-50 rounded-lg">
                                                            <p className="text-sm text-gray-500">No recent requests</p>
                                                        </div>
                                                    </div>
                                                )}
                                            </div>
                                        )}

                                        {/* Leave Requests Tab */}
                                        {activeTab === 'leave' && (
                                            <div className="space-y-3">
                                                {recentLeaveRequests.length > 0 ? (
                                                    recentLeaveRequests.map((request) => (
                                                        <div key={request.id} className="p-4 bg-gray-50 hover:bg-gray-100 rounded-lg border border-gray-200 hover:border-gray-300 transition-all duration-200">
                                                            <div className="flex items-center justify-between">
                                                                <div className="flex-1 min-w-0">
                                                                    <p className="text-sm font-semibold text-gray-900 truncate mb-1">
                                                                        {request.reason}
                                                                    </p>
                                                                    <p className="text-sm text-gray-600">
                                                                        <span className="font-medium">{formatDateWithDay(request.start_date)}</span> to <span className="font-medium">{formatDateWithDay(request.end_date)}</span>
                                                                    </p>
                                                                    <p className="text-xs text-gray-500">
                                                                        {calculateDays(request.start_date, request.end_date)} day{calculateDays(request.start_date, request.end_date) !== 1 ? 's' : ''}
                                                                    </p>
                                                                    <p className="text-xs text-gray-500">
                                                                        {request.employee_name}
                                                                    </p>
                                                                </div>
                                                                <div className="flex items-center space-x-2 ml-4">
                                                                    <span className={`inline-flex items-center px-3 py-1 rounded-full text-xs font-medium ${getStatusColor(request.status, request)}`}>
                                                                        {getStatusDisplayText(request.status, request)}
                                                                    </span>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    ))
                                                ) : (
                                                    <div className="text-center py-8">
                                                        <div className="p-4 bg-gray-50 rounded-lg">
                                                            <p className="text-sm text-gray-500">No recent leave requests</p>
                                                        </div>
                                                    </div>
                                                )}
                                            </div>
                                        )}
                                    </>
                                )}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </AppLayout>
    )
}
