import { Head, Link } from '@inertiajs/react'
import AppLayout from '../Layouts/AppLayout'
import { useState, useEffect } from 'react'

export default function Reports() {
    const [activeTab, setActiveTab] = useState('overview')
    const [dateRange, setDateRange] = useState('30')
    const [loading, setLoading] = useState(false)
    const [stats, setStats] = useState({})
    const [departmentData, setDepartmentData] = useState([])
    const [userData, setUserData] = useState([])
    const [monthlyTrends, setMonthlyTrends] = useState([])
    const [activityLog, setActivityLog] = useState([])

    useEffect(() => {
        loadData()
    }, [dateRange])

    const loadData = async () => {
        setLoading(true)

        // Simulate API calls
        setTimeout(() => {
            setStats({
                total_requests: 156,
                pending_requests: 23,
                approved_requests: 98,
                rejected_requests: 35,
                total_amount: 245000,
                average_amount: 1570.51,
                approval_rate: 73.68,
                average_processing_time: 2.5
            })

            setDepartmentData([
                { department_name: 'IT', total_requests: 45, total_amount: 125000, approved_count: 35, rejected_count: 10 },
                { department_name: 'HR', total_requests: 32, total_amount: 85000, approved_count: 28, rejected_count: 4 },
                { department_name: 'Finance', total_requests: 28, total_amount: 95000, approved_count: 22, rejected_count: 6 },
                { department_name: 'Operations', total_requests: 25, total_amount: 75000, approved_count: 20, rejected_count: 5 },
                { department_name: 'Sales', total_requests: 18, total_amount: 65000, approved_count: 15, rejected_count: 3 },
                { department_name: 'Marketing', total_requests: 8, total_amount: 35000, approved_count: 6, rejected_count: 2 }
            ])

            setUserData([
                { full_name: 'John Doe', department_name: 'IT', total_requests: 12, total_amount: 25000, approved_count: 10 },
                { full_name: 'Jane Smith', department_name: 'HR', total_requests: 8, total_amount: 18000, approved_count: 7 },
                { full_name: 'Mike Johnson', department_name: 'Finance', total_requests: 6, total_amount: 22000, approved_count: 5 },
                { full_name: 'Sarah Wilson', department_name: 'Operations', total_requests: 5, total_amount: 15000, approved_count: 4 },
                { full_name: 'David Brown', department_name: 'Sales', total_requests: 4, total_amount: 12000, approved_count: 3 }
            ])

            setMonthlyTrends([
                { month: '2024-01', total_requests: 45, total_amount: 125000, approved_count: 35 },
                { month: '2024-02', total_requests: 52, total_amount: 145000, approved_count: 42 },
                { month: '2024-03', total_requests: 38, total_amount: 98000, approved_count: 28 },
                { month: '2024-04', total_requests: 41, total_amount: 112000, approved_count: 32 },
                { month: '2024-05', total_requests: 48, total_amount: 138000, approved_count: 38 },
                { month: '2024-06', total_requests: 55, total_amount: 156000, approved_count: 45 }
            ])

            setActivityLog([
                {
                    id: 1,
                    user: { full_name: 'John Doe', role: 'Manager' },
                    action: 'Approved',
                    request: { item: 'Office Supplies' },
                    created_at: '2024-01-15T10:30:00Z'
                },
                {
                    id: 2,
                    user: { full_name: 'Jane Smith', role: 'HR Manager' },
                    action: 'Rejected',
                    request: { item: 'Conference Equipment' },
                    created_at: '2024-01-15T09:15:00Z'
                },
                {
                    id: 3,
                    user: { full_name: 'Mike Johnson', role: 'CEO' },
                    action: 'Approved',
                    request: { item: 'Laptop Computer' },
                    created_at: '2024-01-14T16:45:00Z'
                }
            ])

            setLoading(false)
        }, 1000)
    }

    const formatCurrency = (amount) => {
        return new Intl.NumberFormat('en-AF', {
            style: 'currency',
            currency: 'AFN'
        }).format(amount)
    }

    const formatDate = (dateString) => {
        return new Date(dateString).toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'short',
            day: 'numeric'
        })
    }

    const formatDateTime = (dateString) => {
        return new Date(dateString).toLocaleString('en-US', {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        })
    }

    const tabs = [
        { id: 'overview', name: 'Overview', icon: '📊' },
        { id: 'departments', name: 'By Department', icon: '🏢' },
        { id: 'users', name: 'By User', icon: '👥' },
        { id: 'trends', name: 'Trends', icon: '📈' },
        { id: 'activity', name: 'Activity Log', icon: '📋' }
    ]

    if (loading) {
        return (
            <AppLayout title="Reports">
                <div className="flex items-center justify-center h-64">
                    <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600"></div>
                </div>
            </AppLayout>
        )
    }

    return (
        <AppLayout title="Reports">
            <div className="max-w-7xl mx-auto">
                {/* Header */}
                <div className="mb-8">
                    <h1 className="text-3xl font-bold text-gray-900">Reports & Analytics</h1>
                    <p className="text-gray-600 mt-2">Comprehensive insights into your approval workflow system</p>
                </div>

                {/* Date Range Selector */}
                <div className="bg-white rounded-lg shadow-sm p-6 mb-8">
                    <div className="flex items-center justify-between">
                        <div>
                            <h3 className="text-lg font-medium text-gray-900">Date Range</h3>
                            <p className="text-sm text-gray-500">Select the time period for your reports</p>
                        </div>
                        <div className="flex space-x-2">
                            {['7', '30', '90', '365'].map((days) => (
                                <button
                                    key={days}
                                    onClick={() => setDateRange(days)}
                                    className={`px-4 py-2 rounded-md text-sm font-medium ${
                                        dateRange === days
                                            ? 'bg-blue-600 text-white'
                                            : 'bg-gray-100 text-gray-700 hover:bg-gray-200'
                                    }`}
                                >
                                    {days} days
                                </button>
                            ))}
                        </div>
                    </div>
                </div>

                {/* Tabs */}
                <div className="bg-white rounded-lg shadow-sm mb-8">
                    <div className="border-b border-gray-200">
                        <nav className="-mb-px flex space-x-8 px-6">
                            {tabs.map((tab) => (
                                <button
                                    key={tab.id}
                                    onClick={() => setActiveTab(tab.id)}
                                    className={`py-4 px-1 border-b-2 font-medium text-sm flex items-center space-x-2 ${
                                        activeTab === tab.id
                                            ? 'border-blue-500 text-blue-600'
                                            : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
                                    }`}
                                >
                                    <span>{tab.icon}</span>
                                    <span>{tab.name}</span>
                                </button>
                            ))}
                        </nav>
                    </div>

                    <div className="p-6">
                        {/* Overview Tab */}
                        {activeTab === 'overview' && (
                            <div className="space-y-8">
                                {/* Key Metrics */}
                                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                                    <div className="bg-blue-50 rounded-lg p-6">
                                        <div className="flex items-center">
                                            <div className="p-2 bg-blue-100 rounded-lg">
                                                <span className="text-2xl">📊</span>
                                            </div>
                                            <div className="ml-4">
                                                <p className="text-sm font-medium text-blue-600">Total Requests</p>
                                                <p className="text-2xl font-bold text-blue-900">{stats.total_requests}</p>
                                            </div>
                                        </div>
                                    </div>

                                    <div className="bg-green-50 rounded-lg p-6">
                                        <div className="flex items-center">
                                            <div className="p-2 bg-green-100 rounded-lg">
                                                <span className="text-2xl">✅</span>
                                            </div>
                                            <div className="ml-4">
                                                <p className="text-sm font-medium text-green-600">Approval Rate</p>
                                                <p className="text-2xl font-bold text-green-900">{stats.approval_rate}%</p>
                                            </div>
                                        </div>
                                    </div>

                                    <div className="bg-purple-50 rounded-lg p-6">
                                        <div className="flex items-center">
                                            <div className="p-2 bg-purple-100 rounded-lg">
                                                <span className="text-2xl">💰</span>
                                            </div>
                                            <div className="ml-4">
                                                <p className="text-sm font-medium text-purple-600">Total Amount</p>
                                                <p className="text-2xl font-bold text-purple-900">{formatCurrency(stats.total_amount)}</p>
                                            </div>
                                        </div>
                                    </div>

                                    <div className="bg-orange-50 rounded-lg p-6">
                                        <div className="flex items-center">
                                            <div className="p-2 bg-orange-100 rounded-lg">
                                                <span className="text-2xl">⏱️</span>
                                            </div>
                                            <div className="ml-4">
                                                <p className="text-sm font-medium text-orange-600">Avg Processing Time</p>
                                                <p className="text-2xl font-bold text-orange-900">{stats.average_processing_time} days</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                {/* Status Distribution */}
                                <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
                                    <div className="bg-white border border-gray-200 rounded-lg p-6">
                                        <h3 className="text-lg font-medium text-gray-900 mb-4">Request Status</h3>
                                        <div className="space-y-3">
                                            <div className="flex justify-between items-center">
                                                <span className="text-sm text-gray-600">Approved</span>
                                                <span className="text-sm font-medium text-green-600">{stats.approved_requests}</span>
                                            </div>
                                            <div className="flex justify-between items-center">
                                                <span className="text-sm text-gray-600">Pending</span>
                                                <span className="text-sm font-medium text-yellow-600">{stats.pending_requests}</span>
                                            </div>
                                            <div className="flex justify-between items-center">
                                                <span className="text-sm text-gray-600">Rejected</span>
                                                <span className="text-sm font-medium text-red-600">{stats.rejected_requests}</span>
                                            </div>
                                        </div>
                                    </div>

                                    <div className="bg-white border border-gray-200 rounded-lg p-6">
                                        <h3 className="text-lg font-medium text-gray-900 mb-4">Financial Summary</h3>
                                        <div className="space-y-3">
                                            <div className="flex justify-between items-center">
                                                <span className="text-sm text-gray-600">Total Amount</span>
                                                <span className="text-sm font-medium text-gray-900">{formatCurrency(stats.total_amount)}</span>
                                            </div>
                                            <div className="flex justify-between items-center">
                                                <span className="text-sm text-gray-600">Average Amount</span>
                                                <span className="text-sm font-medium text-gray-900">{formatCurrency(stats.average_amount)}</span>
                                            </div>
                                        </div>
                                    </div>

                                    <div className="bg-white border border-gray-200 rounded-lg p-6">
                                        <h3 className="text-lg font-medium text-gray-900 mb-4">Performance</h3>
                                        <div className="space-y-3">
                                            <div className="flex justify-between items-center">
                                                <span className="text-sm text-gray-600">Approval Rate</span>
                                                <span className="text-sm font-medium text-green-600">{stats.approval_rate}%</span>
                                            </div>
                                            <div className="flex justify-between items-center">
                                                <span className="text-sm text-gray-600">Avg Processing</span>
                                                <span className="text-sm font-medium text-gray-900">{stats.average_processing_time} days</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        )}

                        {/* Departments Tab */}
                        {activeTab === 'departments' && (
                            <div className="space-y-6">
                                <div className="flex justify-between items-center">
                                    <h3 className="text-lg font-medium text-gray-900">Requests by Department</h3>
                                    <button className="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md text-sm font-medium">
                                        Export CSV
                                    </button>
                                </div>
                                <div className="overflow-x-auto">
                                    <table className="min-w-full divide-y divide-gray-200">
                                        <thead className="bg-gray-50">
                                            <tr>
                                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Department</th>
                                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total Requests</th>
                                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total Amount</th>
                                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Approved</th>
                                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Rejected</th>
                                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Approval Rate</th>
                                            </tr>
                                        </thead>
                                        <tbody className="bg-white divide-y divide-gray-200">
                                            {departmentData.map((dept, index) => (
                                                <tr key={index}>
                                                    <td className="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">{dept.department_name}</td>
                                                    <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{dept.total_requests}</td>
                                                    <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{formatCurrency(dept.total_amount)}</td>
                                                    <td className="px-6 py-4 whitespace-nowrap text-sm text-green-600">{dept.approved_count}</td>
                                                    <td className="px-6 py-4 whitespace-nowrap text-sm text-red-600">{dept.rejected_count}</td>
                                                    <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                        {Math.round((dept.approved_count / (dept.approved_count + dept.rejected_count)) * 100)}%
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        )}

                        {/* Users Tab */}
                        {activeTab === 'users' && (
                            <div className="space-y-6">
                                <div className="flex justify-between items-center">
                                    <h3 className="text-lg font-medium text-gray-900">Top Users by Request Count</h3>
                                    <button className="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md text-sm font-medium">
                                        Export CSV
                                    </button>
                                </div>
                                <div className="overflow-x-auto">
                                    <table className="min-w-full divide-y divide-gray-200">
                                        <thead className="bg-gray-50">
                                            <tr>
                                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">User</th>
                                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Department</th>
                                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total Requests</th>
                                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total Amount</th>
                                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Approved</th>
                                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Rejected</th>
                                            </tr>
                                        </thead>
                                        <tbody className="bg-white divide-y divide-gray-200">
                                            {userData.map((user, index) => (
                                                <tr key={index}>
                                                    <td className="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">{user.full_name}</td>
                                                    <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{user.department_name}</td>
                                                    <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{user.total_requests}</td>
                                                    <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{formatCurrency(user.total_amount)}</td>
                                                    <td className="px-6 py-4 whitespace-nowrap text-sm text-green-600">{user.approved_count}</td>
                                                    <td className="px-6 py-4 whitespace-nowrap text-sm text-red-600">{user.total_requests - user.approved_count}</td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        )}

                        {/* Trends Tab */}
                        {activeTab === 'trends' && (
                            <div className="space-y-6">
                                <h3 className="text-lg font-medium text-gray-900">Monthly Trends</h3>
                                <div className="bg-white border border-gray-200 rounded-lg p-6">
                                    <div className="space-y-4">
                                        {monthlyTrends.map((trend, index) => (
                                            <div key={index} className="flex items-center justify-between p-4 bg-gray-50 rounded-lg">
                                                <div>
                                                    <p className="font-medium text-gray-900">{formatDate(trend.month + '-01')}</p>
                                                    <p className="text-sm text-gray-500">{trend.total_requests} requests</p>
                                                </div>
                                                <div className="text-right">
                                                    <p className="font-medium text-gray-900">{formatCurrency(trend.total_amount)}</p>
                                                    <p className="text-sm text-green-600">{trend.approved_count} approved</p>
                                                </div>
                                            </div>
                                        ))}
                                    </div>
                                </div>
                            </div>
                        )}

                        {/* Activity Log Tab */}
                        {activeTab === 'activity' && (
                            <div className="space-y-6">
                                <div className="flex justify-between items-center">
                                    <h3 className="text-lg font-medium text-gray-900">System Activity Log</h3>
                                    <button className="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md text-sm font-medium">
                                        Export CSV
                                    </button>
                                </div>
                                <div className="space-y-4">
                                    {activityLog.map((log) => (
                                        <div key={log.id} className="bg-white border border-gray-200 rounded-lg p-4">
                                            <div className="flex items-center justify-between">
                                                <div className="flex items-center space-x-3">
                                                    <div className={`w-2 h-2 rounded-full ${
                                                        log.action === 'Approved' ? 'bg-green-500' :
                                                        log.action === 'Rejected' ? 'bg-red-500' : 'bg-yellow-500'
                                                    }`}></div>
                                                    <div>
                                                        <p className="text-sm font-medium text-gray-900">
                                                            {log.user.full_name} ({log.user.role}) {log.action} request
                                                        </p>
                                                        <p className="text-sm text-gray-500">{log.request.item}</p>
                                                    </div>
                                                </div>
                                                <div className="text-right">
                                                    <p className="text-sm text-gray-500">{formatDateTime(log.created_at)}</p>
                                                </div>
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            </div>
                        )}
                    </div>
                </div>
            </div>
        </AppLayout>
    )
}
