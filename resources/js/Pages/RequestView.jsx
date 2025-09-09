import { Head, Link, router } from '@inertiajs/react'
import AppLayout from '../Layouts/AppLayout'
import { useState, useEffect } from 'react'
import axios from 'axios'

export default function RequestView({ auth, requestId, source = 'requests' }) {
    const [request, setRequest] = useState(null)
    const [loading, setLoading] = useState(true)
    const [showActionModal, setShowActionModal] = useState(false)
    const [actionType, setActionType] = useState('')
    const [actionNotes, setActionNotes] = useState('')
    const [actionData, setActionData] = useState({
        status: '',
        final_cost: '',
        notes: ''
    })
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

    const handleAction = (action, request = null) => {
        setActionType(action)
        setActionNotes('')

        // Initialize action data based on action type
        if (action === 'procurement') {
            let status = 'Cancelled'
            if (request?.status === 'Approved') {
                status = 'Pending Procurement'
            } else if (request?.status === 'Pending Procurement') {
                status = 'Ordered'
            }

            setActionData({
                status: status,
                final_cost: '',
                notes: ''
            })
        } else if (action === 'order') {
            setActionData({
                status: request?.status === 'Approved' ? 'Pending Procurement' : 'Ordered',
                final_cost: '',
                notes: ''
            })
            setActionType('procurement')
        } else if (action === 'cancel') {
            setActionData({
                status: 'Cancelled',
                final_cost: '',
                notes: ''
            })
            setActionType('procurement')
        } else if (action === 'deliver') {
            setActionData({
                status: 'Delivered',
                final_cost: '',
                notes: ''
            })
            setActionType('procurement')
        } else if (action === 'rollback') {
            setActionData({
                status: 'Pending Procurement',
                final_cost: '',
                notes: ''
            })
            setActionType('procurement')
        } else {
            setActionData({
                status: '',
                final_cost: '',
                notes: ''
            })
        }

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
            } else if (actionType === 'procurement') {
                // Check if this is a rollback action
                if (request.status === 'Cancelled' && actionData.status === 'Pending Procurement') {
                    endpoint = `/api/requests/${request.id}/rollback`
                    data = {
                        notes: actionData.notes
                    }
                } else {
                    endpoint = `/api/requests/${request.id}/process-procurement`
                    data = {
                        status: actionData.status,
                        final_cost: actionData.final_cost,
                        notes: actionData.notes
                    }
                }
            }

            const response = await axios.post(endpoint, data)

            if (response.data.success) {
                setShowActionModal(false)
                setActionType('')
                setActionNotes('')
                setActionData({ status: '', final_cost: '', notes: '' })
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

        // Only show actions for pending requests
        if (request.status !== 'Pending') return false

        // New condition: If manager has already approved/rejected, don't show actions
        if (user.role?.name === 'manager' && hasUserAlreadyApprovedOrRejected()) {
            return false
        }

        // Check if it's user's turn to approve
        if (!isUserTurnToApprove()) return false

        // Admin can do everything
        if (user.role?.name === 'admin') return true

        // Manager can approve/reject requests from their department
        if (user.role?.name === 'manager') {
            return request.employee?.department_id === user.department_id
        }

        return false
    }

    const isUserTurnToApprove = () => {
        const user = auth.user
        if (!user || !request || !request.approval_workflow) return false

        // Check if user has already approved this request
        if (hasUserAlreadyApproved()) return false

        // Check if user is the next approver
        const waitingFor = request.approval_workflow.waiting_for
        if (!waitingFor) return false

        // Check if user's role matches the waiting role
        if (user.role?.name === 'manager' && waitingFor === 'Manager') {
            return request.employee?.department_id === user.department_id
        }
        if (user.role?.name === 'admin' && waitingFor === 'Admin') {
            return true
        }

        return false
    }

    const hasUserAlreadyApproved = () => {
        const user = auth.user
        if (!user || !request || !auditLogs) return false

        // Check if user has already approved this request
        return auditLogs.some(log =>
            log.user_id === user.id &&
            log.action === 'Approved'
        )
    }

    const hasUserAlreadyApprovedOrRejected = () => {
        const user = auth.user
        if (!user || !request || !auditLogs) return false

        // Check if user has already approved or rejected this request
        return auditLogs.some(log =>
            log.user_id === user.id &&
            (log.action === 'Approved' || log.action === 'Rejected')
        )
    }

    const canPerformProcurementAction = () => {
        const user = auth.user
        if (!user || !request) return false

        // Procurement users can process procurement actions
        if (user.role?.name === 'procurement') {
            return ['Approved', 'Pending Procurement', 'Ordered', 'Cancelled'].includes(request.status)
        }

        return false
    }

    const canViewRequest = () => {
        const user = auth.user
        if (!user || !request) return false

        // Admin can see everything
        if (user.role?.name === 'admin') return true

        // Manager can see requests from their department
        if (user.role?.name === 'manager') {
            return request.employee?.department_id === user.department_id
        }

        // Employee can see their own requests
        if (user.role?.name === 'employee') {
            return request.employee_id === user.id
        }

        // Procurement can see their own requests (any status) OR all approved requests
        if (user.role?.name === 'procurement') {
            // If it's their own request, they can see it regardless of status
            if (request.employee_id === user.id) {
                return true
            }
            // Otherwise, they can only see approved requests
            return ['Approved', 'Pending Procurement', 'Ordered', 'Delivered', 'Cancelled'].includes(request.status)
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

    const getStatusDisplayText = (status, approvalWorkflow) => {
        if (status === 'Pending' && approvalWorkflow?.waiting_for) {
            return `Pending (Waiting for ${approvalWorkflow.waiting_for})`
        }
        return status
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
                        href={auth.user?.role?.name === 'procurement' ? '/procurement' : '/requests'}
                        className="inline-flex items-center px-3 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500"
                    >
                        <svg className="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 19l-7-7 7-7" />
                        </svg>
                        Back
                    </Link>
                </div>
            </AppLayout>
        )
    }

    // Check if user can view this request
    if (!canViewRequest()) {
        return (
            <AppLayout title="Access Denied" auth={auth}>
                <div className="text-center py-12">
                    <div className="text-gray-400 text-6xl mb-4">🚫</div>
                    <h3 className="text-lg font-medium text-gray-900 mb-2">Access Denied</h3>
                    <p className="text-gray-500 mb-4">You don't have permission to view this request.</p>
                    <Link
                        href={source === 'procurement' ? '/procurement' : '/requests'}
                        className="inline-flex items-center px-3 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500"
                    >
                        <svg className="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 19l-7-7 7-7" />
                        </svg>
                        Back
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
                    <div className="flex items-center space-x-4">
                        <Link
                            href={source === 'procurement' ? '/procurement' : '/requests'}
                            className="inline-flex items-center px-3 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500"
                        >
                            <svg className="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 19l-7-7 7-7" />
                            </svg>
                            Back
                        </Link>
                        <div>
                            <div className="flex items-center space-x-3">
                                <h1 className="text-2xl font-bold text-gray-900">Request #{request.id}</h1>
                                {source === 'procurement' && (
                                    <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-purple-100 text-purple-800">
                                        Procurement View
                                    </span>
                                )}
                            </div>
                            <p className="text-gray-600">Submitted by {request.employee?.full_name}</p>
                        </div>
                    </div>
                    <div className="flex items-center space-x-3">
                        <span className={`inline-flex items-center px-3 py-1 rounded-full text-sm font-medium ${getStatusColor(request.status)}`}>
                            {getStatusDisplayText(request.status, request.approval_workflow)}
                        </span>
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
                                            {getStatusDisplayText(request.status, request.approval_workflow)}
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

                        {/* Procurement Information */}
                        {auth.user?.role?.name === 'procurement' && request.procurement && (
                            <div className="bg-white shadow-sm rounded-lg p-6">
                                <h3 className="text-lg font-medium text-gray-900 mb-4">Procurement Information</h3>
                                <dl className="grid grid-cols-1 gap-4">
                                    <div>
                                        <dt className="text-sm font-medium text-gray-500">Procurement Status</dt>
                                        <dd className="mt-1">
                                            <span className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${getStatusColor(request.procurement.status)}`}>
                                                {request.procurement.status}
                                            </span>
                                        </dd>
                                    </div>
                                    {request.procurement.final_cost && (
                                        <div>
                                            <dt className="text-sm font-medium text-gray-500">Final Cost</dt>
                                            <dd className="mt-1 text-sm text-gray-900 font-semibold">${parseFloat(request.procurement.final_cost).toFixed(2)}</dd>
                                        </div>
                                    )}
                                    <div>
                                        <dt className="text-sm font-medium text-gray-500">Procurement Started</dt>
                                        <dd className="mt-1 text-sm text-gray-900">{formatDate(request.procurement.created_at)}</dd>
                                    </div>
                                    <div>
                                        <dt className="text-sm font-medium text-gray-500">Last Updated</dt>
                                        <dd className="mt-1 text-sm text-gray-900">{formatDate(request.procurement.updated_at)}</dd>
                                    </div>
                                </dl>
                            </div>
                        )}

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
                        {/* Approval Workflow Status */}
                        {request.approval_workflow && (
                            <div className="bg-white shadow-sm rounded-lg p-6">
                                <h3 className="text-lg font-medium text-gray-900 mb-4">Approval Workflow</h3>
                                <div className="space-y-4">
                                    {/* Workflow Progress */}
                                    <div className="flex items-center justify-between text-sm">
                                        <span className="text-gray-600">Progress</span>
                                        <span className="font-medium">
                                            {request.approval_workflow.current_step} of {request.approval_workflow.total_steps}
                                        </span>
                                    </div>

                                    {/* Progress Bar */}
                                    <div className="w-full bg-gray-200 rounded-full h-2">
                                        <div
                                            className={`h-2 rounded-full transition-all duration-300 ${
                                                request.approval_workflow.current_step === request.approval_workflow.total_steps
                                                    ? 'bg-green-600'
                                                    : 'bg-blue-600'
                                            }`}
                                            style={{
                                                width: `${(request.approval_workflow.current_step / request.approval_workflow.total_steps) * 100}%`
                                            }}
                                        ></div>
                                    </div>

                                    {/* Workflow Steps */}
                                    <div className="space-y-3">
                                        {request.approval_workflow.steps.map((step, index) => (
                                            <div key={index} className="flex items-center space-x-3">
                                                <div className={`w-6 h-6 rounded-full flex items-center justify-center text-xs font-medium ${
                                                    step.status === 'completed' ? 'bg-green-100 text-green-800' :
                                                    step.status === 'pending' ? 'bg-blue-100 text-blue-800' :
                                                    'bg-gray-100 text-gray-500'
                                                }`}>
                                                    {step.status === 'completed' ? '✓' : index + 1}
                                                </div>
                                                <div className="flex-1">
                                                    <div className="flex items-center justify-between">
                                                        <span className={`text-sm font-medium ${
                                                            step.status === 'completed' ? 'text-green-800' :
                                                            step.status === 'pending' ? 'text-blue-800' :
                                                            'text-gray-500'
                                                        }`}>
                                                            {step.role}
                                                        </span>
                                                        <span className={`text-xs px-2 py-1 rounded-full ${
                                                            step.status === 'completed' ? 'bg-green-100 text-green-800' :
                                                            step.status === 'pending' ? 'bg-blue-100 text-blue-800' :
                                                            'bg-gray-100 text-gray-500'
                                                        }`}>
                                                            {step.status === 'completed' ? 'Completed' :
                                                             step.status === 'pending' ? 'Pending' :
                                                             'Waiting'}
                                                        </span>
                                                    </div>
                                                    <p className="text-xs text-gray-500 mt-1">{step.description}</p>
                                                    {step.approver && (
                                                        <p className="text-xs text-gray-400 mt-1">Approver: {step.approver}</p>
                                                    )}
                                                </div>
                                            </div>
                                        ))}
                                    </div>

                                    {/* Waiting Status */}
                                    {request.approval_workflow.waiting_for && (
                                        <div className="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                                            <div className="flex items-center">
                                                <div className="flex-shrink-0">
                                                    <svg className="h-5 w-5 text-yellow-400" fill="currentColor" viewBox="0 0 20 20">
                                                        <path fillRule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clipRule="evenodd" />
                                                    </svg>
                                                </div>
                                                <div className="ml-3">
                                                    <p className="text-sm font-medium text-yellow-800">
                                                        ⏳ Waiting for {request.approval_workflow.waiting_for} approval
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                    )}
                                </div>
                            </div>
                        )}

                        {/* Actions */}
                        {request.status === 'Pending' && (
                            <div className="bg-white shadow-sm rounded-lg p-6">
                                <h3 className="text-lg font-medium text-gray-900 mb-4">Actions</h3>
                                {canPerformAction() ? (
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
                                ) : hasUserAlreadyApproved() ? (
                                    <div className="bg-green-50 border border-green-200 rounded-lg p-4">
                                        <div className="flex items-center">
                                            <div className="flex-shrink-0">
                                                <svg className="h-5 w-5 text-green-400" fill="currentColor" viewBox="0 0 20 20">
                                                    <path fillRule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clipRule="evenodd" />
                                                </svg>
                                            </div>
                                            <div className="ml-3">
                                                <p className="text-sm font-medium text-green-800">
                                                    ✅ You have already approved this request
                                                </p>
                                                <p className="text-sm text-green-700 mt-1">
                                                    Waiting for other approvers to complete the workflow
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                ) : (
                                    <div className="bg-gray-50 border border-gray-200 rounded-lg p-4">
                                        <div className="flex items-center">
                                            <div className="flex-shrink-0">
                                                <svg className="h-5 w-5 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                                                    <path fillRule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clipRule="evenodd" />
                                                </svg>
                                            </div>
                                            <div className="ml-3">
                                                <p className="text-sm font-medium text-gray-800">
                                                    ⏳ Waiting for your turn to approve
                                                </p>
                                                <p className="text-sm text-gray-700 mt-1">
                                                    {request.approval_workflow?.waiting_for
                                                        ? `This request is waiting for ${request.approval_workflow.waiting_for} approval`
                                                        : 'This request is waiting for previous approvers in the workflow'
                                                    }
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                )}
                            </div>
                        )}

                        {/* Procurement Actions */}
                        {canPerformProcurementAction() && (
                            <div className="bg-white shadow-sm rounded-lg p-6">
                                <h3 className="text-lg font-medium text-gray-900 mb-4">Procurement Actions</h3>
                                <div className="space-y-3">
                                    {/* Action Buttons based on status */}
                                    {(request.status === 'Approved' || request.status === 'Pending Procurement') && (
                                        <div className="flex space-x-2">
                                            <button
                                                onClick={() => handleAction('order', request)}
                                                className="flex-1 bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-md text-sm font-medium text-center"
                                            >
                                                {request.status === 'Approved' ? '🚀 Start Procurement' : '📦 Order'}
                                            </button>
                                            <button
                                                onClick={() => handleAction('cancel', request)}
                                                className="flex-1 bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-md text-sm font-medium text-center"
                                            >
                                                ❌ Cancel
                                            </button>
                                        </div>
                                    )}

                                    {request.status === 'Ordered' && (
                                        <div className="flex space-x-2">
                                            <button
                                                onClick={() => handleAction('deliver', request)}
                                                className="flex-1 bg-purple-600 hover:bg-purple-700 text-white px-4 py-2 rounded-md text-sm font-medium text-center"
                                            >
                                                🚚 Deliver
                                            </button>
                                        </div>
                                    )}

                                    {request.status === 'Cancelled' && (
                                        <div className="flex space-x-2">
                                            <button
                                                onClick={() => handleAction('rollback', request)}
                                                className="flex-1 bg-orange-600 hover:bg-orange-700 text-white px-4 py-2 rounded-md text-sm font-medium text-center"
                                            >
                                                🔄 Restore Request
                                            </button>
                                        </div>
                                    )}
                                </div>
                            </div>
                        )}


                        {/* Quick Actions */}
                        <div className="bg-white shadow-sm rounded-lg p-6">
                            <h3 className="text-lg font-medium text-gray-900 mb-4">Quick Actions</h3>
                            <div className="space-y-2">
                                <button
                                    onClick={() => window.print()}
                                    className="w-full text-left text-sm text-blue-600 hover:text-blue-800"
                                >
                                    🖨️ Print Request
                                </button>
                                <button
                                    onClick={() => {
                                        const url = window.location.href
                                        navigator.clipboard.writeText(url)
                                        alert('Request URL copied to clipboard')
                                    }}
                                    className="w-full text-left text-sm text-blue-600 hover:text-blue-800"
                                >
                                    📋 Copy Link
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                {/* Action Modal */}
                {showActionModal && (
                    <div className="fixed inset-0 modal-backdrop overflow-y-auto h-full w-full z-50 flex items-center justify-center p-4">
                        <div className="relative w-full max-w-2xl bg-white rounded-lg shadow-xl">
                            <div className="p-6">
                                <div className="flex items-center justify-center w-12 h-12 mx-auto bg-blue-100 rounded-full mb-4">
                                    <svg className="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                </div>
                                <div className="text-center mb-6">
                                    <h3 className="text-lg font-medium text-gray-900">
                                        {actionType === 'approve' ? 'Approve Request' :
                                         actionType === 'reject' ? 'Reject Request' :
                                         actionType === 'procurement' ?
                                            (request.status === 'Cancelled' && actionData.status === 'Pending Procurement' ? 'Restore Request' :
                                             actionData.status === 'Pending Procurement' ? 'Start Procurement Process' :
                                             actionData.status === 'Ordered' ? 'Mark as Ordered' :
                                             actionData.status === 'Delivered' ? 'Mark as Delivered' :
                                             actionData.status === 'Cancelled' ? 'Cancel Request' : 'Procurement Action') : 'Action'}
                                    </h3>
                                </div>
                                <div className="space-y-4">
                                        {actionType === 'procurement' ? (
                                            <div className="space-y-4">
                                                <p className="text-sm text-gray-500 text-center">
                                                    {request.status === 'Cancelled' && actionData.status === 'Pending Procurement' ? 'Restore this cancelled request to pending procurement status' :
                                                     actionData.status === 'Pending Procurement' ? 'Start procurement process for this request' :
                                                     actionData.status === 'Ordered' ? 'Mark this request as ordered' :
                                                     actionData.status === 'Delivered' ? 'Mark this request as delivered' :
                                                     actionData.status === 'Cancelled' ? 'Cancel this request' :
                                                     'Process procurement action'}
                                                </p>
                                                <div>
                                                    <label className="block text-sm font-medium text-gray-700 mb-2">
                                                        Final Cost (Optional)
                                                    </label>
                                                    <input
                                                        type="number"
                                                        step="0.01"
                                                        min="0"
                                                        value={actionData.final_cost}
                                                        onChange={(e) => setActionData(prev => ({ ...prev, final_cost: e.target.value }))}
                                                        className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
                                                        placeholder="0.00"
                                                    />
                                                </div>
                                                <div>
                                                    <label className="block text-sm font-medium text-gray-700 mb-2">
                                                        Notes
                                                    </label>
                                                    <textarea
                                                        value={actionData.notes}
                                                        onChange={(e) => setActionData(prev => ({ ...prev, notes: e.target.value }))}
                                                        rows={3}
                                                        className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
                                                        placeholder="Add any notes about this action..."
                                                    />
                                                </div>
                                            </div>
                                        ) : (
                                            <>
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
                                            </>
                                        )}
                                </div>
                                <div className="flex justify-end space-x-3 pt-6 border-t border-gray-200">
                                    <button
                                        onClick={() => setShowActionModal(false)}
                                        className="px-4 py-2 bg-gray-500 text-white text-base font-medium rounded-md shadow-sm hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-gray-300"
                                    >
                                        Cancel
                                    </button>
                                    <button
                                        onClick={submitAction}
                                        disabled={actionLoading ||
                                            (actionType === 'reject' && !actionNotes.trim()) ||
                                            (actionType === 'procurement' && !actionData.status)
                                        }
                                        className={`px-4 py-2 text-white text-base font-medium rounded-md shadow-sm focus:outline-none focus:ring-2 ${
                                            actionType === 'approve'
                                                ? 'bg-green-600 hover:bg-green-700 focus:ring-green-300'
                                                : actionType === 'reject'
                                                ? 'bg-red-600 hover:bg-red-700 focus:ring-red-300'
                                                : 'bg-blue-600 hover:bg-blue-700 focus:ring-blue-300'
                                        } ${actionLoading ? 'opacity-50 cursor-not-allowed' : ''}`}
                                    >
                                        {actionLoading ? 'Processing...' :
                                         actionType === 'approve' ? 'Approve' :
                                         actionType === 'reject' ? 'Reject' :
                                         'Process'}
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                )}
            </div>
        </AppLayout>
    )
}
