import { Head, Link, router } from '@inertiajs/react'
import AppLayout from '../Layouts/AppLayout'
import { useState, useEffect } from 'react'
import axios from 'axios'

export default function RequestView({ auth, requestId }) {
    const [request, setRequest] = useState(null)
    const [loading, setLoading] = useState(true)
    const [showActionModal, setShowActionModal] = useState(false)
    const [actionType, setActionType] = useState('')
    const [actionNotes, setActionNotes] = useState('')
    const [actionLoading, setActionLoading] = useState(false)
    const [auditLogs, setAuditLogs] = useState([])

    useEffect(() => {
        fetchRequestDetails()
    }, [requestId])

    const fetchRequestDetails = async () => {
        try {
            setLoading(true)
            const response = await axios.get(`/api/requests/${requestId}`)
            if (response.data.success) {
                setRequest(response.data.data)
                fetchAuditLogs()
            }
        } catch (error) {
            console.error('Error fetching request details:', error)
            if (error.response?.status === 403) {
                alert('You are not authorized to view this request')
                router.visit('/requests')
            }
        } finally {
            setLoading(false)
        }
    }

    const fetchAuditLogs = async () => {
        try {
            const response = await axios.get(`/api/requests/${requestId}/audit-logs`)
            if (response.data.success) {
                setAuditLogs(response.data.data)
            }
        } catch (error) {
            console.error('Error fetching audit logs:', error)
        }
    }

    const handleAction = (action) => {
        setActionType(action)
        setActionNotes('')
        setShowActionModal(true)
    }

    const submitAction = async () => {
        if (!request || !actionType) return

        try {
            setActionLoading(true)
            let endpoint = ''
            let data = {}

            if (actionType === 'approve') {
                endpoint = `/api/requests/${request.id}/approve`
                data = { notes: actionNotes }
            } else if (actionType === 'reject') {
                endpoint = `/api/requests/${request.id}/reject`
                data = { reason: actionNotes }
            } else if (actionType === 'process-procurement') {
                endpoint = `/api/requests/${request.id}/process-procurement`
                data = {
                    status: 'Ordered',
                    notes: actionNotes
                }
            }

            const response = await axios.post(endpoint, data)

            if (response.data.success) {
                setShowActionModal(false)
                setActionType('')
                setActionNotes('')
                fetchRequestDetails() // Refresh the request data
            }
        } catch (error) {
            console.error('Error performing action:', error)
            alert('Error performing action: ' + (error.response?.data?.message || error.message))
        } finally {
            setActionLoading(false)
        }
    }

    const canPerformAction = () => {
        const user = auth.user
        if (!user || !request) return false

        // Admin can do everything
        if (user.role?.name === 'admin') return true

        // Manager can approve/reject requests from their department
        if (user.role?.name === 'manager') {
            return request.employee?.department_id === user.department_id
        }

        return false
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

    const formatDate = (dateString) => {
        return new Date(dateString).toLocaleString()
    }

    if (loading) {
        return (
            <AppLayout title="Request Details" auth={auth}>
                <div className="flex items-center justify-center h-64">
                    <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600"></div>
                </div>
            </AppLayout>
        )
    }

    if (!request) {
        return (
            <AppLayout title="Request Not Found" auth={auth}>
                <div className="text-center py-12">
                    <div className="text-gray-400 text-6xl mb-4">❌</div>
                    <h3 className="text-lg font-medium text-gray-900 mb-2">Request not found</h3>
                    <p className="text-gray-500 mb-4">The request you're looking for doesn't exist or you don't have permission to view it.</p>
                    <Link
                        href="/requests"
                        className="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md font-medium"
                    >
                        Back to Requests
                    </Link>
                </div>
            </AppLayout>
        )
    }

    return (
        <AppLayout title={`Request #${request.id}`} auth={auth}>
            <div className="space-y-6">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold text-gray-900">Request #{request.id}</h1>
                        <p className="text-gray-600">Submitted by {request.employee?.full_name}</p>
                    </div>
                    <div className="flex items-center space-x-3">
                        <span className={`inline-flex items-center px-3 py-1 rounded-full text-sm font-medium ${getStatusColor(request.status)}`}>
                            {request.status}
                        </span>
                        <Link
                            href="/requests"
                            className="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-md font-medium"
                        >
                            Back to Requests
                        </Link>
                    </div>
                </div>

                {/* Request Details */}
                <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    {/* Main Information */}
                    <div className="lg:col-span-2 space-y-6">
                        {/* Basic Details */}
                        <div className="bg-white shadow-sm rounded-lg p-6">
                            <h3 className="text-lg font-medium text-gray-900 mb-4">Request Details</h3>
                            <dl className="grid grid-cols-1 gap-4">
                                <div>
                                    <dt className="text-sm font-medium text-gray-500">Item</dt>
                                    <dd className="mt-1 text-sm text-gray-900">{request.item}</dd>
                                </div>
                                <div>
                                    <dt className="text-sm font-medium text-gray-500">Description</dt>
                                    <dd className="mt-1 text-sm text-gray-900">{request.description}</dd>
                                </div>
                                <div>
                                    <dt className="text-sm font-medium text-gray-500">Amount</dt>
                                    <dd className="mt-1 text-sm text-gray-900 font-semibold">${parseFloat(request.amount).toFixed(2)}</dd>
                                </div>
                                <div>
                                    <dt className="text-sm font-medium text-gray-500">Status</dt>
                                    <dd className="mt-1">
                                        <span className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${getStatusColor(request.status)}`}>
                                            {request.status}
                                        </span>
                                    </dd>
                                </div>
                                <div>
                                    <dt className="text-sm font-medium text-gray-500">Created</dt>
                                    <dd className="mt-1 text-sm text-gray-900">{formatDate(request.created_at)}</dd>
                                </div>
                                <div>
                                    <dt className="text-sm font-medium text-gray-500">Last Updated</dt>
                                    <dd className="mt-1 text-sm text-gray-900">{formatDate(request.updated_at)}</dd>
                                </div>
                            </dl>
                        </div>

                        {/* Employee Information */}
                        <div className="bg-white shadow-sm rounded-lg p-6">
                            <h3 className="text-lg font-medium text-gray-900 mb-4">Employee Information</h3>
                            <dl className="grid grid-cols-1 gap-4">
                                <div>
                                    <dt className="text-sm font-medium text-gray-500">Name</dt>
                                    <dd className="mt-1 text-sm text-gray-900">{request.employee?.full_name}</dd>
                                </div>
                                <div>
                                    <dt className="text-sm font-medium text-gray-500">Email</dt>
                                    <dd className="mt-1 text-sm text-gray-900">{request.employee?.email}</dd>
                                </div>
                                <div>
                                    <dt className="text-sm font-medium text-gray-500">Department</dt>
                                    <dd className="mt-1 text-sm text-gray-900">{request.employee?.department?.name}</dd>
                                </div>
                                <div>
                                    <dt className="text-sm font-medium text-gray-500">Role</dt>
                                    <dd className="mt-1 text-sm text-gray-900 capitalize">{request.employee?.role?.name}</dd>
                                </div>
                            </dl>
                        </div>

                        {/* Audit Trail */}
                        <div className="bg-white shadow-sm rounded-lg p-6">
                            <h3 className="text-lg font-medium text-gray-900 mb-4">Audit Trail</h3>
                            {auditLogs.length > 0 ? (
                                <div className="space-y-4">
                                    {auditLogs.map((log, index) => (
                                        <div key={index} className="flex items-start space-x-3">
                                            <div className="flex-shrink-0">
                                                <div className="w-2 h-2 bg-blue-400 rounded-full mt-2"></div>
                                            </div>
                                            <div className="flex-1 min-w-0">
                                                <div className="flex items-center justify-between">
                                                    <p className="text-sm font-medium text-gray-900">{log.action}</p>
                                                    <p className="text-xs text-gray-500">{formatDate(log.created_at)}</p>
                                                </div>
                                                <p className="text-sm text-gray-600">{log.user?.full_name}</p>
                                                {log.notes && (
                                                    <p className="text-sm text-gray-500 mt-1">{log.notes}</p>
                                                )}
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            ) : (
                                <p className="text-sm text-gray-500">No audit logs available</p>
                            )}
                        </div>
                    </div>

                    {/* Actions Sidebar */}
                    <div className="space-y-6">
                        {/* Actions */}
                        {canPerformAction() && request.status === 'Pending' && (
                            <div className="bg-white shadow-sm rounded-lg p-6">
                                <h3 className="text-lg font-medium text-gray-900 mb-4">Actions</h3>
                                <div className="space-y-3">
                                    <button
                                        onClick={() => handleAction('approve')}
                                        className="w-full bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-md font-medium"
                                    >
                                        Approve Request
                                    </button>
                                    <button
                                        onClick={() => handleAction('reject')}
                                        className="w-full bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-md font-medium"
                                    >
                                        Reject Request
                                    </button>
                                </div>
                            </div>
                        )}

                        {/* Procurement Actions */}
                        {auth.user?.role?.name === 'procurement' && request.status === 'Pending Procurement' && (
                            <div className="bg-white shadow-sm rounded-lg p-6">
                                <h3 className="text-lg font-medium text-gray-900 mb-4">Procurement Actions</h3>
                                <div className="space-y-3">
                                    <button
                                        onClick={() => handleAction('process-procurement')}
                                        className="w-full bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md font-medium"
                                    >
                                        Process Request
                                    </button>
                                </div>
                            </div>
                        )}

                        {/* Status Information */}
                        <div className="bg-white shadow-sm rounded-lg p-6">
                            <h3 className="text-lg font-medium text-gray-900 mb-4">Status Information</h3>
                            <div className="space-y-3">
                                <div className="flex items-center justify-between">
                                    <span className="text-sm text-gray-500">Current Status</span>
                                    <span className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${getStatusColor(request.status)}`}>
                                        {request.status}
                                    </span>
                                </div>
                                <div className="flex items-center justify-between">
                                    <span className="text-sm text-gray-500">Request ID</span>
                                    <span className="text-sm font-mono text-gray-900">#{request.id}</span>
                                </div>
                                <div className="flex items-center justify-between">
                                    <span className="text-sm text-gray-500">Amount</span>
                                    <span className="text-sm font-semibold text-gray-900">${parseFloat(request.amount).toFixed(2)}</span>
                                </div>
                            </div>
                        </div>

                        {/* Quick Actions */}
                        <div className="bg-white shadow-sm rounded-lg p-6">
                            <h3 className="text-lg font-medium text-gray-900 mb-4">Quick Actions</h3>
                            <div className="space-y-2">
                                <button
                                    onClick={() => window.print()}
                                    className="w-full text-left text-sm text-blue-600 hover:text-blue-800"
                                >
                                    Print Request
                                </button>
                                <button
                                    onClick={() => {
                                        const url = window.location.href
                                        navigator.clipboard.writeText(url)
                                        alert('Request URL copied to clipboard')
                                    }}
                                    className="w-full text-left text-sm text-blue-600 hover:text-blue-800"
                                >
                                    Copy Link
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                {/* Action Modal */}
                {showActionModal && (
                    <div className="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
                        <div className="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
                            <div className="mt-3">
                                <div className="flex items-center justify-center w-12 h-12 mx-auto bg-blue-100 rounded-full">
                                    <svg className="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                </div>
                                <div className="mt-2 px-7 py-3">
                                    <h3 className="text-lg font-medium text-gray-900 text-center">
                                        {actionType === 'approve' ? 'Approve Request' : 'Reject Request'}
                                    </h3>
                                    <div className="mt-2 px-7 py-3">
                                        <p className="text-sm text-gray-500 text-center">
                                            {actionType === 'approve'
                                                ? 'Are you sure you want to approve this request?'
                                                : 'Are you sure you want to reject this request?'
                                            }
                                        </p>
                                        <div className="mt-4">
                                            <label className="block text-sm font-medium text-gray-700 mb-2">
                                                {actionType === 'approve' ? 'Notes (optional)' : 'Reason (required)'}
                                            </label>
                                            <textarea
                                                value={actionNotes}
                                                onChange={(e) => setActionNotes(e.target.value)}
                                                rows={3}
                                                className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
                                                placeholder={actionType === 'approve' ? 'Add any notes...' : 'Please provide a reason for rejection...'}
                                                required={actionType === 'reject'}
                                            />
                                        </div>
                                    </div>
                                    <div className="items-center px-4 py-3">
                                        <div className="flex space-x-3">
                                            <button
                                                onClick={() => setShowActionModal(false)}
                                                className="px-4 py-2 bg-gray-500 text-white text-base font-medium rounded-md shadow-sm hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-gray-300"
                                            >
                                                Cancel
                                            </button>
                                            <button
                                                onClick={submitAction}
                                                disabled={actionLoading || (actionType === 'reject' && !actionNotes.trim())}
                                                className={`px-4 py-2 text-white text-base font-medium rounded-md shadow-sm focus:outline-none focus:ring-2 ${
                                                    actionType === 'approve'
                                                        ? 'bg-green-600 hover:bg-green-700 focus:ring-green-300'
                                                        : 'bg-red-600 hover:bg-red-700 focus:ring-red-300'
                                                } ${actionLoading ? 'opacity-50 cursor-not-allowed' : ''}`}
                                            >
                                                {actionLoading ? 'Processing...' : (actionType === 'approve' ? 'Approve' : 'Reject')}
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                )}
            </div>
        </AppLayout>
    )
}
