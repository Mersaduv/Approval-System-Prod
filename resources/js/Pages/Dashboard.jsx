import { Head, Link } from '@inertiajs/react'
import AppLayout from '../Layouts/AppLayout'
import { useState, useEffect } from 'react'

export default function Dashboard({ auth }) {
    const [stats, setStats] = useState({
        totalRequests: 0,
        pendingRequests: 0,
        approvedRequests: 0,
        rejectedRequests: 0,
        myRequests: 0,
        pendingApprovals: 0
    })
    const [recentRequests, setRecentRequests] = useState([])
    const [loading, setLoading] = useState(true)

    useEffect(() => {
        // Simulate API call
        setTimeout(() => {
            setStats({
                totalRequests: 156,
                pendingRequests: 23,
                approvedRequests: 98,
                rejectedRequests: 35,
                myRequests: 12,
                pendingApprovals: 5
            })
            setRecentRequests([
                {
                    id: 1,
                    item: 'Office Supplies',
                    amount: 1500,
                    status: 'Pending',
                    created_at: '2024-01-15'
                },
                {
                    id: 2,
                    item: 'Laptop Computer',
                    amount: 8500,
                    status: 'Approved',
                    created_at: '2024-01-14'
                },
                {
                    id: 3,
                    item: 'Conference Equipment',
                    amount: 3200,
                    status: 'Rejected',
                    created_at: '2024-01-13'
                }
            ])
            setLoading(false)
        }, 1000)
    }, [])

    const getStatusColor = (status) => {
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

    if (loading) {
        return (
            <AppLayout title="Dashboard" auth={auth}>
                <div className="flex items-center justify-center h-64">
                    <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600"></div>
                </div>
            </AppLayout>
        )
    }

    return (
        <AppLayout title="Dashboard" auth={auth}>
            <div className="space-y-6">
                {/* Header */}
                <div>
                    <h1 className="text-2xl font-bold text-gray-900">Dashboard</h1>
                    <p className="text-gray-600 mt-1">Welcome back! Here's what's happening with your requests.</p>
                </div>

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

                        {/* Notifications - Available for all users */}
                        <Link
                            href="/notifications"
                            className="flex items-center p-3 lg:p-4 border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors"
                        >
                            <div className="p-2 bg-yellow-100 rounded-lg mr-3">
                                <span className="text-lg lg:text-xl">🔔</span>
                            </div>
                            <div className="min-w-0 flex-1">
                                <p className="font-medium text-gray-900 text-sm lg:text-base">Notifications</p>
                                <p className="text-xs lg:text-sm text-gray-500">View notifications</p>
                            </div>
                        </Link>

                        {/* Reports - Available for all users */}
                        <Link
                            href="/reports"
                            className="flex items-center p-3 lg:p-4 border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors"
                        >
                            <div className="p-2 bg-purple-100 rounded-lg mr-3">
                                <span className="text-lg lg:text-xl">📊</span>
                            </div>
                            <div className="min-w-0 flex-1">
                                <p className="font-medium text-gray-900 text-sm lg:text-base">Reports</p>
                                <p className="text-xs lg:text-sm text-gray-500">Generate reports</p>
                            </div>
                        </Link>
                    </div>
                </div>

                {/* Recent Requests */}
                <div className="grid grid-cols-1 lg:grid-cols-2 gap-6 lg:gap-8">
                    <div className="bg-white rounded-lg shadow-sm">
                        <div className="px-4 lg:px-6 py-4 border-b border-gray-200">
                            <div className="flex items-center justify-between">
                                <h3 className="text-base lg:text-lg font-medium text-gray-900">Recent Requests</h3>
                                <Link
                                    href="/requests"
                                    className="text-xs lg:text-sm text-blue-600 hover:text-blue-800"
                                >
                                    View all
                                </Link>
                            </div>
                        </div>
                        <div className="p-4 lg:p-6">
                            <div className="space-y-4">
                                {recentRequests.map((request) => (
                                    <div key={request.id} className="flex items-center justify-between">
                                        <div className="flex-1 min-w-0">
                                            <p className="text-sm font-medium text-gray-900 truncate">
                                                {request.item}
                                            </p>
                                            <p className="text-sm text-gray-500">
                                                {request.amount.toLocaleString()} AFN
                                            </p>
                                        </div>
                                        <div className="flex items-center space-x-2">
                                            <span className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${getStatusColor(request.status)}`}>
                                                {request.status}
                                            </span>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </div>
                    </div>

                    {/* System Status */}
                    <div className="bg-white rounded-lg shadow-sm">
                        <div className="px-4 lg:px-6 py-4 border-b border-gray-200">
                            <h3 className="text-base lg:text-lg font-medium text-gray-900">System Status</h3>
                        </div>
                        <div className="p-4 lg:p-6">
                            <div className="space-y-4">
                                <div className="flex items-center justify-between">
                                    <span className="text-sm text-gray-600">System Health</span>
                                    <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                        <span className="w-2 h-2 bg-green-400 rounded-full mr-1"></span>
                                        Operational
                                    </span>
                                </div>
                                <div className="flex items-center justify-between">
                                    <span className="text-sm text-gray-600">Last Backup</span>
                                    <span className="text-sm text-gray-900">2 hours ago</span>
                                </div>
                                <div className="flex items-center justify-between">
                                    <span className="text-sm text-gray-600">Active Users</span>
                                    <span className="text-sm text-gray-900">24</span>
                                </div>
                                <div className="flex items-center justify-between">
                                    <span className="text-sm text-gray-600">Uptime</span>
                                    <span className="text-sm text-gray-900">99.9%</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </AppLayout>
    )
}
