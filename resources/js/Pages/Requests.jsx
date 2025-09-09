import { Head, Link, router } from '@inertiajs/react'
import AppLayout from '../Layouts/AppLayout'
import { useState, useEffect } from 'react'
import axios from 'axios'

export default function Requests({ auth }) {
    const [requests, setRequests] = useState([])
    const [loading, setLoading] = useState(true)
    const [searchTerm, setSearchTerm] = useState('')
    const [statusFilter, setStatusFilter] = useState('all')

    useEffect(() => {
        fetchRequests()
    }, [statusFilter])

    const fetchRequests = async () => {
        try {
            setLoading(true)
            const params = new URLSearchParams()
            if (statusFilter !== 'all') {
                params.append('status', statusFilter)
            }

            const response = await axios.get(`/api/requests?${params.toString()}`)
            if (response.data.success) {
                setRequests(response.data.data.data || response.data.data)
            }
        } catch (error) {
            console.error('Error fetching requests:', error)
        } finally {
            setLoading(false)
        }
    }

    const filteredRequests = requests.filter(request => {
        const employeeName = request.employee?.full_name || request.employee?.name || ''
        const matchesSearch = request.item.toLowerCase().includes(searchTerm.toLowerCase()) ||
                            employeeName.toLowerCase().includes(searchTerm.toLowerCase())
        return matchesSearch
    })


    const canSubmitRequest = () => {
        const user = auth.user
        return user && (user.role?.name === 'admin' || user.role?.name === 'manager' || user.role?.name === 'employee')
    }

    const getStatusColor = (status) => {
        switch (status.toLowerCase()) {
            case 'pending': return 'bg-yellow-100 text-yellow-800'
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
            <AppLayout title="Requests" auth={auth}>
                <div className="flex items-center justify-center h-64">
                    <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600"></div>
                </div>
            </AppLayout>
        )
    }

    return (
        <AppLayout title="Requests" auth={auth}>
            <div className="space-y-6">
                {/* Search, Filter and New Request */}
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div className="flex flex-col sm:flex-row sm:items-center gap-4">
                        <div className="flex-1 max-w-md">
                            <div className="relative">
                                <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <svg className="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                    </svg>
                                </div>
                                <input
                                    type="text"
                                    placeholder="Search requests..."
                                    value={searchTerm}
                                    onChange={(e) => setSearchTerm(e.target.value)}
                                    className="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-md leading-5 bg-white placeholder-gray-500 focus:outline-none focus:placeholder-gray-400 focus:ring-1 focus:ring-blue-500 focus:border-blue-500 text-sm"
                                />
                            </div>
                        </div>
                        <select
                            value={statusFilter}
                            onChange={(e) => setStatusFilter(e.target.value)}
                            className="block w-full sm:w-40 px-3 py-2 border border-gray-300 rounded-md bg-white focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500 text-sm"
                        >
                            <option value="all">All Status</option>
                            <option value="Pending">Pending</option>
                            <option value="Approved">Approved</option>
                            <option value="Rejected">Rejected</option>
                            <option value="Pending Procurement">Pending Procurement</option>
                            <option value="Ordered">Ordered</option>
                            <option value="Delivered">Delivered</option>
                            <option value="Cancelled">Cancelled</option>
                        </select>
                    </div>
                    {canSubmitRequest() && (
                        <Link
                            href="/requests/new"
                            className="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md font-medium text-center sm:text-left"
                        >
                            New Request
                        </Link>
                    )}
                </div>

                {/* Requests Table - Desktop */}
                <div className="hidden lg:block bg-white shadow-sm rounded-lg overflow-hidden">
                    <table className="min-w-full divide-y divide-gray-200">
                        <thead className="bg-gray-50">
                            <tr>
                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    ID
                                </th>
                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Employee
                                </th>
                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Department
                                </th>
                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Item
                                </th>
                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Amount
                                </th>
                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Status
                                </th>
                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Date
                                </th>
                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Action
                                </th>
                            </tr>
                        </thead>
                        <tbody className="bg-white divide-y divide-gray-200">
                            {filteredRequests.map((request) => (
                                <tr key={request.id} className="hover:bg-gray-50">
                                    <td className="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                        #{request.id}
                                    </td>
                                    <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        {request.employee?.full_name || 'N/A'}
                                    </td>
                                    <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        {request.employee?.department?.name || 'N/A'}
                                    </td>
                                    <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <div className="max-w-xs truncate" title={request.item}>
                                            {request.item}
                                        </div>
                                    </td>
                                    <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        ${parseFloat(request.amount).toFixed(2)}
                                    </td>
                                    <td className="px-6 py-4 whitespace-nowrap">
                                        <span className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${getStatusColor(request.status)}`}>
                                            {request.status}
                                        </span>
                                    </td>
                                    <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        {new Date(request.created_at).toLocaleDateString()}
                                    </td>
                                    <td className="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <Link
                                            href={`/requests/${request.id}`}
                                            className="text-blue-600 hover:text-blue-900 bg-blue-50 hover:bg-blue-100 px-3 py-1 rounded-md text-xs"
                                        >
                                            View
                                        </Link>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                {/* Requests Cards - Mobile */}
                <div className="lg:hidden space-y-4">
                    {filteredRequests.map((request) => (
                        <div key={request.id} className="bg-white shadow-sm rounded-lg p-4 border border-gray-200">
                            <div className="flex items-start justify-between mb-3">
                                <div className="flex-1 min-w-0">
                                    <h3 className="text-sm font-medium text-gray-900 truncate">
                                        {request.item}
                                    </h3>
                                    <p className="text-xs text-gray-500">#{request.id}</p>
                                </div>
                                <span className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${getStatusColor(request.status)}`}>
                                    {request.status}
                                </span>
                            </div>

                            <div className="space-y-2 text-sm">
                                <div className="flex justify-between">
                                    <span className="text-gray-500">Employee:</span>
                                    <span className="text-gray-900">{request.employee?.full_name || 'N/A'}</span>
                                </div>
                                <div className="flex justify-between">
                                    <span className="text-gray-500">Department:</span>
                                    <span className="text-gray-900">{request.employee?.department?.name || 'N/A'}</span>
                                </div>
                                <div className="flex justify-between">
                                    <span className="text-gray-500">Amount:</span>
                                    <span className="text-gray-900 font-medium">${parseFloat(request.amount).toFixed(2)}</span>
                                </div>
                                <div className="flex justify-between">
                                    <span className="text-gray-500">Date:</span>
                                    <span className="text-gray-900">{new Date(request.created_at).toLocaleDateString()}</span>
                                </div>
                            </div>

                            <div className="mt-4 pt-3 border-t border-gray-200">
                                <Link
                                    href={`/requests/${request.id}`}
                                    className="w-full bg-blue-50 hover:bg-blue-100 text-blue-600 hover:text-blue-900 px-3 py-2 rounded-md text-xs font-medium text-center block"
                                >
                                    View Details
                                </Link>
                            </div>
                        </div>
                    ))}
                </div>

                {filteredRequests.length === 0 && (
                    <div className="text-center py-12">
                        <div className="text-gray-400 text-6xl mb-4">📄</div>
                        <h3 className="text-lg font-medium text-gray-900 mb-2">No requests found</h3>
                        <p className="text-gray-500">
                            {searchTerm
                                ? 'Try adjusting your search criteria'
                                : 'Get started by creating your first request'
                            }
                        </p>
                    </div>
                )}

            </div>
        </AppLayout>
    )
}
