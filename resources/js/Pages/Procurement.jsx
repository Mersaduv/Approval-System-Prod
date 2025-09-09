import { Head, Link, router } from '@inertiajs/react'
import AppLayout from '../Layouts/AppLayout'
import { useState, useEffect } from 'react'
import axios from 'axios'

export default function Procurement({ auth }) {
    const [requests, setRequests] = useState([])
    const [loading, setLoading] = useState(true)
    const [searchTerm, setSearchTerm] = useState('')
    const [statusFilter, setStatusFilter] = useState('all')
    const [showActionModal, setShowActionModal] = useState(false)
    const [selectedRequest, setSelectedRequest] = useState(null)
    const [actionType, setActionType] = useState('')
    const [actionData, setActionData] = useState({
        status: '',
        final_cost: '',
        notes: ''
    })
    const [actionLoading, setActionLoading] = useState(false)

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

            const response = await axios.get(`/api/requests/pending/procurement?${params.toString()}`)
            if (response.data.success) {
                setRequests(response.data.data)
            }
        } catch (error) {
            console.error('Error fetching procurement requests:', error)
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

    const handleAction = (request, action) => {
        setSelectedRequest(request)
        setActionType(action)

        let status = 'Cancelled'
        if (action === 'order') {
            status = request.status === 'Approved' ? 'Pending Procurement' : 'Ordered'
        } else if (action === 'deliver') {
            status = 'Delivered'
        }

        setActionData({
            status: status,
            final_cost: '',
            notes: ''
        })
        setShowActionModal(true)
    }

    const submitAction = async () => {
        if (!selectedRequest || !actionType) return

        try {
            setActionLoading(true)
            const response = await axios.post(`/api/requests/${selectedRequest.id}/process-procurement`, actionData)

            if (response.data.success) {
                setShowActionModal(false)
                setSelectedRequest(null)
                setActionType('')
                setActionData({ status: '', final_cost: '', notes: '' })
                fetchRequests() // Refresh the requests
            }
        } catch (error) {
            console.error('Error processing procurement action:', error)
            alert('Error processing action: ' + (error.response?.data?.message || error.message))
        } finally {
            setActionLoading(false)
        }
    }

    const getStatusColor = (status) => {
        switch (status.toLowerCase()) {
            case 'approved': return 'bg-green-100 text-green-800'
            case 'pending procurement': return 'bg-blue-100 text-blue-800'
            case 'ordered': return 'bg-purple-100 text-purple-800'
            case 'delivered': return 'bg-green-100 text-green-800'
            case 'cancelled': return 'bg-red-100 text-red-800'
            default: return 'bg-gray-100 text-gray-800'
        }
    }

    if (loading) {
        return (
            <AppLayout title="Procurement" auth={auth}>
                <div className="flex items-center justify-center h-64">
                    <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600"></div>
                </div>
            </AppLayout>
        )
    }

    return (
        <AppLayout title="Procurement Management" auth={auth}>
            <div className="space-y-6">
                {/* Header */}
                <div>
                    <h1 className="text-2xl font-bold text-gray-900">Procurement Management</h1>
                    <p className="text-gray-600 mt-1">Manage approved requests and procurement processes.</p>
                </div>

                {/* Search and Filter */}
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
                        <option value="Approved">Approved</option>
                        <option value="Pending Procurement">Pending Procurement</option>
                        <option value="Ordered">Ordered</option>
                        <option value="Delivered">Delivered</option>
                        <option value="Cancelled">Cancelled</option>
                    </select>
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
                                    Actions
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
                                        <div className="flex space-x-2">
                                            <Link
                                                href={`/requests/${request.id}`}
                                                className="text-blue-600 hover:text-blue-900 bg-blue-50 hover:bg-blue-100 px-3 py-1 rounded-md text-xs"
                                            >
                                                View
                                            </Link>
                                            {(request.status === 'Approved' || request.status === 'Pending Procurement') && (
                                                <>
                                                    <button
                                                        onClick={() => handleAction(request, 'order')}
                                                        className="text-green-600 hover:text-green-900 bg-green-50 hover:bg-green-100 px-3 py-1 rounded-md text-xs"
                                                    >
                                                        {request.status === 'Approved' ? 'Start Procurement' : 'Order'}
                                                    </button>
                                                    <button
                                                        onClick={() => handleAction(request, 'cancel')}
                                                        className="text-red-600 hover:text-red-900 bg-red-50 hover:bg-red-100 px-3 py-1 rounded-md text-xs"
                                                    >
                                                        Cancel
                                                    </button>
                                                </>
                                            )}
                                            {request.status === 'Ordered' && (
                                                <button
                                                    onClick={() => handleAction(request, 'deliver')}
                                                    className="text-purple-600 hover:text-purple-900 bg-purple-50 hover:bg-purple-100 px-3 py-1 rounded-md text-xs"
                                                >
                                                    Deliver
                                                </button>
                                            )}
                                        </div>
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

                            <div className="mt-4 pt-3 border-t border-gray-200 flex space-x-2">
                                <Link
                                    href={`/requests/${request.id}`}
                                    className="flex-1 bg-blue-50 hover:bg-blue-100 text-blue-600 hover:text-blue-900 px-3 py-2 rounded-md text-xs font-medium text-center"
                                >
                                    View Details
                                </Link>
                                {(request.status === 'Approved' || request.status === 'Pending Procurement') && (
                                    <>
                                        <button
                                            onClick={() => handleAction(request, 'order')}
                                            className="flex-1 bg-green-50 hover:bg-green-100 text-green-600 hover:text-green-900 px-3 py-2 rounded-md text-xs font-medium text-center"
                                        >
                                            {request.status === 'Approved' ? 'Start Procurement' : 'Order'}
                                        </button>
                                        <button
                                            onClick={() => handleAction(request, 'cancel')}
                                            className="flex-1 bg-red-50 hover:bg-red-100 text-red-600 hover:text-red-900 px-3 py-2 rounded-md text-xs font-medium text-center"
                                        >
                                            Cancel
                                        </button>
                                    </>
                                )}
                                {request.status === 'Ordered' && (
                                    <button
                                        onClick={() => handleAction(request, 'deliver')}
                                        className="flex-1 bg-purple-50 hover:bg-purple-100 text-purple-600 hover:text-purple-900 px-3 py-2 rounded-md text-xs font-medium text-center"
                                    >
                                        Deliver
                                    </button>
                                )}
                            </div>
                        </div>
                    ))}
                </div>

                {filteredRequests.length === 0 && (
                    <div className="text-center py-12">
                        <div className="text-gray-400 text-6xl mb-4">📦</div>
                        <h3 className="text-lg font-medium text-gray-900 mb-2">No procurement requests found</h3>
                        <p className="text-gray-500">
                            {searchTerm
                                ? 'Try adjusting your search criteria'
                                : 'No requests are currently pending procurement processing'
                            }
                        </p>
                    </div>
                )}

                {/* Action Modal */}
                {showActionModal && selectedRequest && (
                    <div className="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
                        <div className="relative top-10 sm:top-20 mx-auto p-4 sm:p-5 border w-11/12 sm:w-3/4 md:w-1/2 lg:w-1/3 shadow-lg rounded-md bg-white">
                            <div className="mt-3">
                                <h3 className="text-lg font-medium text-gray-900 mb-4">
                                    {actionType === 'order' ? (selectedRequest?.status === 'Approved' ? 'Start Procurement Process' : 'Mark as Ordered') :
                                     actionType === 'deliver' ? 'Mark as Delivered' :
                                     'Cancel Request'}
                                </h3>
                                <form onSubmit={(e) => { e.preventDefault(); submitAction(); }} className="space-y-4">
                                    <div>
                                        <label className="block text-sm font-medium text-gray-700 mb-1">
                                            Final Cost (Optional)
                                        </label>
                                        <input
                                            type="number"
                                            step="0.01"
                                            min="0"
                                            value={actionData.final_cost}
                                            onChange={(e) => setActionData(prev => ({ ...prev, final_cost: e.target.value }))}
                                            className="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
                                            placeholder="0.00"
                                        />
                                    </div>
                                    <div>
                                        <label className="block text-sm font-medium text-gray-700 mb-1">
                                            Notes
                                        </label>
                                        <textarea
                                            value={actionData.notes}
                                            onChange={(e) => setActionData(prev => ({ ...prev, notes: e.target.value }))}
                                            rows={3}
                                            className="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
                                            placeholder="Add any notes about this action..."
                                        />
                                    </div>
                                    <div className="flex justify-end space-x-3 pt-4">
                                        <button
                                            type="button"
                                            onClick={() => setShowActionModal(false)}
                                            className="px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50"
                                        >
                                            Cancel
                                        </button>
                                        <button
                                            type="submit"
                                            disabled={actionLoading}
                                            className="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-md text-sm font-medium disabled:opacity-50"
                                        >
                                            {actionLoading ? 'Processing...' : 'Confirm'}
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                )}
            </div>
        </AppLayout>
    )
}
