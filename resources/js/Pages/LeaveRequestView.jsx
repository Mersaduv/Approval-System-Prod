import { Head, Link, router } from '@inertiajs/react'
import AppLayout from '../Layouts/AppLayout'
import { useState, useEffect } from 'react'
import axios from 'axios'
import AlertModal from '../Components/AlertModal'
import AuditTrailGraph from '../Components/AuditTrailGraph'

export default function LeaveRequestView({ leaveRequestId, auth }) {
    const [leaveRequest, setLeaveRequest] = useState(null)
    const [workflowSteps, setWorkflowSteps] = useState([])
    const [auditLogs, setAuditLogs] = useState([])
    const [loading, setLoading] = useState(true)
    const [actionLoading, setActionLoading] = useState(false)
    const [showApprovalModal, setShowApprovalModal] = useState(false)
    const [showRejectionModal, setShowRejectionModal] = useState(false)
    const [approvalNotes, setApprovalNotes] = useState('')
    const [rejectionReason, setRejectionReason] = useState('')
    const [permissions, setPermissions] = useState({
        can_approve: false,
        can_reject: false
    })
    const [showAlert, setShowAlert] = useState(false)
    const [alertMessage, setAlertMessage] = useState('')
    const [alertType, setAlertType] = useState('info')

    const showAlertMessage = (message, type = 'info') => {
        setAlertMessage(message)
        setAlertType(type)
        setShowAlert(true)
    }

    useEffect(() => {
        fetchLeaveRequest()
    }, [leaveRequestId])

    const fetchLeaveRequest = async () => {
        try {
            setLoading(true)
            const response = await axios.get(`/api/leave-requests/${leaveRequestId}`)
            if (response.data.success) {
                setLeaveRequest(response.data.data.leave_request)
                setWorkflowSteps(response.data.data.workflow_steps || [])
                setPermissions({
                    can_approve: response.data.data.can_approve,
                    can_reject: response.data.data.can_reject
                })

                // Set audit logs from leave request
                setAuditLogs(response.data.data.leave_request.audit_logs || [])
            }
        } catch (error) {
            console.error('Error fetching leave request:', error)
            showAlertMessage('Error loading leave request details', 'error')
        } finally {
            setLoading(false)
        }
    }

    const handleApprove = async () => {
        setActionLoading(true)
        try {
            const response = await axios.post(`/api/leave-requests/${leaveRequestId}/approve`, {
                notes: approvalNotes
            })

            if (response.data.success) {
                setShowApprovalModal(false)
                setApprovalNotes('')
                showAlertMessage('Leave request approved successfully', 'success')
                await fetchLeaveRequest() // Refresh data
            }
        } catch (error) {
            console.error('Error approving leave request:', error)
            showAlertMessage('Error approving leave request', 'error')
        } finally {
            setActionLoading(false)
        }
    }

    const handleReject = async () => {
        setActionLoading(true)
        try {
            const response = await axios.post(`/api/leave-requests/${leaveRequestId}/reject`, {
                reason: rejectionReason
            })

            if (response.data.success) {
                setShowRejectionModal(false)
                setRejectionReason('')
                showAlertMessage('Leave request rejected successfully', 'success')
                await fetchLeaveRequest() // Refresh data
            }
        } catch (error) {
            console.error('Error rejecting leave request:', error)
            showAlertMessage('Error rejecting leave request', 'error')
        } finally {
            setActionLoading(false)
        }
    }


    const getStatusColor = (status) => {
        switch (status?.toLowerCase()) {
            case 'pending': return 'bg-yellow-100 text-yellow-800'
            case 'pending approval': return 'bg-orange-100 text-orange-800'
            case 'approved': return 'bg-green-100 text-green-800'
            case 'rejected': return 'bg-red-100 text-red-800'
            case 'cancelled': return 'bg-gray-100 text-gray-800'
            default: return 'bg-gray-100 text-gray-800'
        }
    }

    const getStepStatusColor = (status) => {
        switch (status?.toLowerCase()) {
            case 'completed': return 'text-green-600'
            case 'rejected': return 'text-red-600'
            case 'pending': return 'text-yellow-600'
            default: return 'text-gray-600'
        }
    }

    const getStepIcon = (status) => {
        switch (status?.toLowerCase()) {
            case 'completed':
                return (
                    <svg className="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
                    </svg>
                )
            case 'rejected':
                return (
                    <svg className="w-5 h-5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                    </svg>
                )
            case 'pending':
                return (
                    <svg className="w-5 h-5 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                )
            default:
                return (
                    <svg className="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                )
        }
    }

    const formatDate = (dateString) => {
        if (!dateString) return 'N/A'
        return new Date(dateString).toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        })
    }

    const formatDateRange = (startDate, endDate) => {
        const start = new Date(startDate).toLocaleDateString()
        const end = new Date(endDate).toLocaleDateString()
        return `${start} - ${end}`
    }

    const getCompletedStepsCount = () => {
        return workflowSteps.filter(step => step.status === 'completed').length
    }


    const handlePrint = () => {
        window.print()
    }

    if (loading) {
        return (
            <AppLayout title="Leave Request Details" auth={auth}>
                <div className="flex items-center justify-center h-64">
                    <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600"></div>
                </div>
            </AppLayout>
        )
    }

    if (!leaveRequest) {
        return (
            <AppLayout title="Leave Request Not Found" auth={auth}>
                <div className="max-w-4xl mx-auto text-center py-12">
                    <h1 className="text-2xl font-bold text-gray-900 mb-4">Leave Request Not Found</h1>
                    <p className="text-gray-600 mb-8">The leave request you're looking for doesn't exist or you don't have permission to view it.</p>
                    <Link
                        href="/leave-requests"
                        className="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md font-medium"
                    >
                        Back to Leave Requests
                    </Link>
                </div>
            </AppLayout>
        )
    }

    return (
        <AppLayout title={`Leave Request #${leaveRequest.id}`} auth={auth}>
            <Head title={`Leave Request #${leaveRequest.id}`} />

            <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
                {/* Header */}
                <div className="mb-8">
                    <div className="flex items-center justify-between">
                        <div className="flex items-center space-x-4">
                            <Link
                                href="/leave-requests"
                                className="text-gray-400 hover:text-gray-600"
                            >
                                <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 19l-7-7 7-7" />
                                </svg>
                            </Link>
                            <div>
                                <h1 className="text-2xl font-bold text-gray-900">
                                    Leave Request #{leaveRequest.id}
                                </h1>
                                <p className="text-sm text-gray-500 mt-1">
                                    Submitted on {formatDate(leaveRequest.created_at)}
                                </p>
                            </div>
                        </div>
                        <div className="flex items-center space-x-4">
                            <span className={`inline-flex items-center px-3 py-1 rounded-full text-sm font-medium ${getStatusColor(leaveRequest.status)}`}>
                                {leaveRequest.status}
                            </span>
                        </div>
                    </div>
                </div>

                {/* Main Content */}
                <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    {/* Main Information */}
                    <div className="lg:col-span-2 space-y-6">
                        {/* Request Details */}
                        <div className="bg-white shadow-sm rounded-lg p-6">
                            <h3 className="text-lg font-medium text-gray-900 mb-4">
                                Request Details
                            </h3>
                            <dl className="grid grid-cols-1 gap-4">
                                <div>
                                    <dt className="text-sm font-medium text-gray-500">
                                        Reason for Leave
                                    </dt>
                                    <dd className="mt-1 text-sm text-gray-900 whitespace-pre-wrap">
                                        {leaveRequest.reason}
                                    </dd>
                                </div>
                                <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                                    <div>
                                        <dt className="text-sm font-medium text-gray-500">
                                            Start Date
                                        </dt>
                                        <dd className="mt-1 text-sm text-gray-900">
                                            {new Date(leaveRequest.start_date).toLocaleDateString()}
                                        </dd>
                                    </div>
                                    <div>
                                        <dt className="text-sm font-medium text-gray-500">
                                            End Date
                                        </dt>
                                        <dd className="mt-1 text-sm text-gray-900">
                                            {new Date(leaveRequest.end_date).toLocaleDateString()}
                                        </dd>
                                    </div>
                                    <div>
                                        <dt className="text-sm font-medium text-gray-500">
                                            Total Days
                                        </dt>
                                        <dd className="mt-1 text-sm text-gray-900 font-medium">
                                            {leaveRequest.total_days} day{leaveRequest.total_days !== 1 ? 's' : ''}
                                        </dd>
                                    </div>
                                </div>

                                {/* Manager Notes */}
                                {leaveRequest.manager_notes && (
                                    <div>
                                        <dt className="text-sm font-medium text-gray-500">
                                            Manager Notes
                                        </dt>
                                        <dd className="mt-1 text-sm text-gray-900 whitespace-pre-wrap">
                                            {leaveRequest.manager_notes}
                                        </dd>
                                    </div>
                                )}

                                {/* Rejection Reason */}
                                {leaveRequest.rejection_reason && (
                                    <div>
                                        <dt className="text-sm font-medium text-gray-500">
                                            Rejection Reason
                                        </dt>
                                        <dd className="mt-1 text-sm text-red-900 whitespace-pre-wrap">
                                            {leaveRequest.rejection_reason}
                                        </dd>
                                    </div>
                                )}
                            </dl>
                        </div>

                        {/* Employee Information */}
                        <div className="bg-white shadow-sm rounded-lg p-6">
                            <h3 className="text-lg font-medium text-gray-900 mb-4">
                                Employee Information
                            </h3>
                            <dl className="grid grid-cols-1 gap-4">
                                <div>
                                    <dt className="text-sm font-medium text-gray-500">
                                        Name
                                    </dt>
                                    <dd className="mt-1 text-sm text-gray-900">
                                        {leaveRequest.employee?.full_name || 'Unknown Employee'}
                                    </dd>
                                </div>
                                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <dt className="text-sm font-medium text-gray-500">
                                            Department
                                        </dt>
                                        <dd className="mt-1 text-sm text-gray-900">
                                            {leaveRequest.employee?.department?.name || 'N/A'}
                                        </dd>
                                    </div>
                                    <div>
                                        <dt className="text-sm font-medium text-gray-500">
                                            Role
                                        </dt>
                                        <dd className="mt-1 text-sm text-gray-900 capitalize">
                                            {leaveRequest.employee?.role?.name || 'N/A'}
                                        </dd>
                                    </div>
                                </div>
                            </dl>
                        </div>

                        {/* Audit Trail */}
                        <div className="bg-white shadow-sm rounded-lg p-6">
                            <h3 className="text-lg font-medium text-gray-900 mb-6">
                                Audit Trail
                            </h3>
                            <AuditTrailGraph auditLogs={auditLogs} formatDate={formatDate} />
                        </div>
                    </div>

                    {/* Actions Sidebar */}
                    <div className="space-y-6">
                        {/* Approval Workflow Status - Exact same as RequestView */}
                        {workflowSteps.length > 0 && (
                            <div className="bg-white shadow-sm rounded-lg p-6">
                                <h3 className="text-lg font-medium text-gray-900 mb-4">
                                    Approval Workflow
                                </h3>
                                <div className="space-y-4">
                                    {/* Workflow Progress */}
                                    <div className="flex items-center justify-between text-sm">
                                        <span className="text-gray-600">
                                            Progress
                                        </span>
                                        <span className="font-medium">
                                            {getCompletedStepsCount()}{" "}
                                            of{" "}
                                            {workflowSteps.length}
                                        </span>
                                    </div>

                                    {/* Progress Bar */}
                                    <div className="w-full bg-gray-200 rounded-full h-2">
                                        <div
                                            className={`h-2 rounded-full transition-all duration-300 ${
                                                getCompletedStepsCount() === workflowSteps.length
                                                    ? "bg-green-600"
                                                    : "bg-blue-600"
                                            }`}
                                            style={{
                                                width: `${
                                                    (getCompletedStepsCount() / workflowSteps.length) * 100
                                                }%`,
                                            }}
                                        ></div>
                                    </div>

                                    {/* Workflow Steps */}
                                    <div className="space-y-3">
                                        {workflowSteps.map(
                                            (step, index) => (
                                                <div
                                                    key={step.id || index}
                                                    className="flex items-center space-x-3"
                                                >
                                                    <div
                                                        className={`w-6 h-6 rounded-full flex items-center justify-center text-xs font-medium ${
                                                            step.status ===
                                                            "completed"
                                                                ? "bg-green-100 text-green-800"
                                                                : step.status ===
                                                                  "pending"
                                                                ? "bg-blue-100 text-blue-800"
                                                                : step.status ===
                                                                  "rejected"
                                                                ? "bg-red-100 text-red-800"
                                                                : step.status ===
                                                                  "cancelled"
                                                                ? "bg-orange-100 text-orange-800"
                                                                : "bg-gray-100 text-gray-500"
                                                        }`}
                                                    >
                                                        {step.status ===
                                                        "completed"
                                                            ? "✓"
                                                            : step.status ===
                                                              "rejected"
                                                            ? "✗"
                                                            : step.status ===
                                                              "cancelled"
                                                            ? "✕"
                                                            : step.order_index !==
                                                              undefined
                                                            ? step.order_index + 1
                                                            : index + 1}
                                                </div>
                                                <div className="flex-1">
                                                    <div className="flex items-center justify-between">
                                                        <div className="flex items-center gap-2">
                                                                <span
                                                                    className={`text-sm font-medium ${
                                                                        step.status ===
                                                                        "completed"
                                                                            ? "text-green-800"
                                                                            : step.status ===
                                                                              "pending"
                                                                            ? "text-blue-800"
                                                                            : step.status ===
                                                                              "rejected"
                                                                            ? "text-red-800"
                                                                            : step.status ===
                                                                              "cancelled"
                                                                            ? "text-orange-800"
                                                                            : "text-gray-500"
                                                                    }`}
                                                                >
                                                                    {step.name}
                                                            </span>
                                                            {step.step_type && (
                                                                    <span
                                                                        className={`text-xs px-2 py-1 rounded-full ${
                                                                            step.step_type ===
                                                                            "approval"
                                                                                ? "bg-blue-100 text-blue-800"
                                                                                : step.step_type ===
                                                                                  "verification"
                                                                                ? "bg-yellow-100 text-yellow-800"
                                                                                : step.step_type ===
                                                                                  "notification"
                                                                                ? "bg-purple-100 text-purple-800"
                                                                                : "bg-gray-100 text-gray-600"
                                                                        }`}
                                                                    >
                                                                        {step.step_type ===
                                                                        "approval"
                                                                            ? "Approval"
                                                                            : step.step_type ===
                                                                              "verification"
                                                                            ? "Verification"
                                                                            : step.step_type ===
                                                                              "notification"
                                                                            ? "Notification"
                                                                            : step.step_type}
                                                                </span>
                                                            )}
                                                        </div>
                                                            <span
                                                                className={`text-xs px-2 py-1 rounded-full ${
                                                                    step.status ===
                                                                    "completed"
                                                                        ? "bg-green-100 text-green-800"
                                                                        : step.status ===
                                                                          "pending"
                                                                        ? "bg-blue-100 text-blue-800"
                                                                        : step.status ===
                                                                          "rejected"
                                                                        ? "bg-red-100 text-red-800"
                                                                        : step.status ===
                                                                          "cancelled"
                                                                        ? "bg-orange-100 text-orange-800"
                                                                        : "bg-gray-100 text-gray-500"
                                                                }`}
                                                            >
                                                                {step.status ===
                                                                "completed"
                                                                    ? "Completed"
                                                                    : step.status ===
                                                                      "pending"
                                                                    ? "Pending"
                                                                    : step.status ===
                                                                      "rejected"
                                                                    ? "Rejected"
                                                                    : step.status ===
                                                                      "cancelled"
                                                                    ? "Cancelled"
                                                                    : "Waiting"}
                                                        </span>
                                                    </div>
                                                        <p className="text-xs text-gray-500 mt-1">
                                                            {step.description}
                                                        </p>
                                                    <div className="flex items-center gap-4 mt-1">
                                                            {step.completed_by && (
                                                                <p className="text-xs text-gray-400">
                                                                    Approver:{" "}
                                                                    {
                                                                        step.completed_by.full_name
                                                                    }
                                                                </p>
                                                        )}
                                                        {step.completed_at && (
                                                                <p className="text-xs text-gray-400">
                                                                    {formatDate(step.completed_at)}
                                                                </p>
                                                        )}
                                                    </div>
                                                </div>
                                            </div>
                                            )
                                        )}
                                    </div>
                                </div>
                            </div>
                        )}

                        {/* Actions - Only show if user has any actions available */}
                        {(permissions.can_approve || permissions.can_reject) && (
                            <div className="bg-white shadow-sm rounded-lg p-6">
                                <h3 className="text-lg font-medium text-gray-900 mb-4">Actions</h3>

                                <div className="space-y-3">
                                    {permissions.can_approve && (
                                        <button
                                            onClick={() => setShowApprovalModal(true)}
                                            disabled={actionLoading}
                                            className="w-full bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-md font-medium disabled:opacity-50 flex items-center justify-center space-x-2"
                                        >
                                            <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
                                            </svg>
                                            <span>Approve</span>
                                        </button>
                                    )}

                                    {permissions.can_reject && (
                                        <button
                                            onClick={() => setShowRejectionModal(true)}
                                            disabled={actionLoading}
                                            className="w-full bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-md font-medium disabled:opacity-50 flex items-center justify-center space-x-2"
                                        >
                                            <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                                            </svg>
                                            <span>Reject</span>
                                        </button>
                                    )}
                                </div>
                            </div>
                        )}

                        {/* Request Information */}
                        {/* <div className="bg-white shadow-sm rounded-lg p-6">
                            <h3 className="text-lg font-medium text-gray-900 mb-4">Request Information</h3>

                            <div className="space-y-3 text-sm">
                                <div className="flex justify-between">
                                    <span className="text-gray-500">Submitted:</span>
                                    <span className="text-gray-900">
                                        {formatDate(leaveRequest.created_at)}
                                    </span>
                                </div>

                                {leaveRequest.approved_at && (
                                    <div className="flex justify-between">
                                        <span className="text-gray-500">Approved:</span>
                                        <span className="text-gray-900">
                                            {formatDate(leaveRequest.approved_at)}
                                        </span>
                                    </div>
                                )}

                                {leaveRequest.rejected_at && (
                                    <div className="flex justify-between">
                                        <span className="text-gray-500">Rejected:</span>
                                        <span className="text-gray-900">
                                            {formatDate(leaveRequest.rejected_at)}
                                        </span>
                                    </div>
                                )}

                                {leaveRequest.approved_by && (
                                    <div className="flex justify-between">
                                        <span className="text-gray-500">Approved by:</span>
                                        <span className="text-gray-900">
                                            {leaveRequest.approved_by.full_name}
                                        </span>
                                    </div>
                                )}

                                {leaveRequest.rejected_by && (
                                    <div className="flex justify-between">
                                        <span className="text-gray-500">Rejected by:</span>
                                        <span className="text-gray-900">
                                            {leaveRequest.rejected_by.full_name}
                                        </span>
                                    </div>
                                )}
                            </div>
                        </div> */}
                    </div>
                </div>
            </div>

            {/* Approval Modal */}
            {showApprovalModal && (
                <div className="fixed inset-0 modal-backdrop overflow-y-auto h-full w-full z-50 flex items-center justify-center p-4">
                    <div className="relative w-full max-w-2xl bg-white rounded-lg shadow-xl">
                        <div className="p-6">
                            <div className="flex items-center justify-center w-12 h-12 mx-auto bg-green-100 rounded-full mb-4">
                                <svg
                                    className="w-6 h-6 text-green-600"
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        strokeLinecap="round"
                                        strokeLinejoin="round"
                                        strokeWidth={2}
                                        d="M5 13l4 4L19 7"
                                    />
                                </svg>
                            </div>
                            <div className="text-center mb-6">
                                <h3 className="text-lg font-medium text-gray-900">
                                    Approve Leave Request
                                </h3>
                            </div>
                            <div className="space-y-4">
                                <p className="text-sm text-gray-500 text-center">
                                    You are about to approve this leave request. Please add any notes if needed.
                                </p>
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-2">
                                        Notes (Optional)
                                    </label>
                                    <textarea
                                        value={approvalNotes}
                                        onChange={(e) => setApprovalNotes(e.target.value)}
                                        rows={3}
                                        className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-green-500 focus:border-green-500"
                                        placeholder="Add any notes about this approval..."
                                    />
                                </div>
                            </div>
                            <div className="flex justify-end space-x-3 pt-6 border-t border-gray-200">
                                <button
                                    onClick={() => setShowApprovalModal(false)}
                                    className="px-4 py-2 bg-gray-500 text-white text-base font-medium rounded-md shadow-sm hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-gray-300"
                                >
                                    Cancel
                                </button>
                                <button
                                    onClick={handleApprove}
                                    disabled={actionLoading}
                                    className="px-4 py-2 bg-green-600 text-white text-base font-medium rounded-md shadow-sm hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 disabled:opacity-50"
                                >
                                    {actionLoading ? 'Approving...' : 'Approve'}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            )}

            {/* Rejection Modal */}
            {showRejectionModal && (
                <div className="fixed inset-0 modal-backdrop overflow-y-auto h-full w-full z-50 flex items-center justify-center p-4">
                    <div className="relative w-full max-w-2xl bg-white rounded-lg shadow-xl">
                        <div className="p-6">
                            <div className="flex items-center justify-center w-12 h-12 mx-auto bg-red-100 rounded-full mb-4">
                                <svg
                                    className="w-6 h-6 text-red-600"
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        strokeLinecap="round"
                                        strokeLinejoin="round"
                                        strokeWidth={2}
                                        d="M6 18L18 6M6 6l12 12"
                                    />
                                </svg>
                            </div>
                            <div className="text-center mb-6">
                                <h3 className="text-lg font-medium text-gray-900">
                                    Reject Leave Request
                                </h3>
                            </div>
                            <div className="space-y-4">
                                <p className="text-sm text-gray-500 text-center">
                                    You are about to reject this leave request. Please provide a reason for rejection.
                                </p>
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-2">
                                        Rejection Reason *
                                    </label>
                                    <textarea
                                        value={rejectionReason}
                                        onChange={(e) => setRejectionReason(e.target.value)}
                                        rows={3}
                                        className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-red-500 focus:border-red-500"
                                        placeholder="Please provide a reason for rejection..."
                                        required
                                    />
                                </div>
                            </div>
                            <div className="flex justify-end space-x-3 pt-6 border-t border-gray-200">
                                <button
                                    onClick={() => setShowRejectionModal(false)}
                                    className="px-4 py-2 bg-gray-500 text-white text-base font-medium rounded-md shadow-sm hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-gray-300"
                                >
                                    Cancel
                                </button>
                                <button
                                    onClick={handleReject}
                                    disabled={actionLoading || !rejectionReason.trim()}
                                    className="px-4 py-2 bg-red-600 text-white text-base font-medium rounded-md shadow-sm hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 disabled:opacity-50"
                                >
                                    {actionLoading ? 'Rejecting...' : 'Reject'}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            )}

            {/* Alert Modal */}
            <AlertModal
                show={showAlert}
                onClose={() => setShowAlert(false)}
                title={alertType === 'error' ? 'Error' : alertType === 'success' ? 'Success' : 'Information'}
                message={alertMessage}
                type={alertType}
            />
        </AppLayout>
    )
}
