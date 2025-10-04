import { Head, Link } from '@inertiajs/react'
import AppLayout from '../Layouts/AppLayout'
import { useState, useEffect } from 'react'
import axios from 'axios'
import AlertModal from '../Components/AlertModal'

export default function ProcurementVerification({ auth }) {
    const [requests, setRequests] = useState([])
    const [loading, setLoading] = useState(true)
    const [selectedRequest, setSelectedRequest] = useState(null)
    const [showModal, setShowModal] = useState(false)
    const [verificationData, setVerificationData] = useState({
        status: 'Verified',
        final_price: '',
        notes: ''
    })
    const [isProcessing, setIsProcessing] = useState(false)
    const [showAlert, setShowAlert] = useState(false)
    const [alertMessage, setAlertMessage] = useState('')
    const [alertType, setAlertType] = useState('info')

    const showAlertMessage = (message, type = 'info') => {
        setAlertMessage(message)
        setAlertType(type)
        setShowAlert(true)
    }

    useEffect(() => {
        fetchRequests()
    }, [])

    const fetchRequests = async () => {
        try {
            const response = await axios.get('/api/requests/pending/verification')
            if (response.data.success) {
                setRequests(response.data.data)
            }
        } catch (error) {
            console.error('Error fetching requests:', error)
        } finally {
            setLoading(false)
        }
    }

    const handleVerification = (request, action) => {
        setSelectedRequest(request)
        setVerificationData({
            status: action,
            final_price: action === 'Verified' ? request.amount : '',
            notes: ''
        })
        setShowModal(true)
    }

    const handleSubmitVerification = async () => {
        if (!selectedRequest) return

        setIsProcessing(true)
        try {
            const response = await axios.post(`/api/requests/${selectedRequest.id}/verify`, verificationData)
            if (response.data.success) {
                setShowModal(false)
                setSelectedRequest(null)
                fetchRequests() // Refresh the list
            } else {
                showAlertMessage('Error: ' + response.data.message, 'error')
            }
        } catch (error) {
            console.error('Error processing verification:', error)
            showAlertMessage('Error processing verification: ' + (error.response?.data?.message || error.message), 'error')
        } finally {
            setIsProcessing(false)
        }
    }

    const getStatusColor = (status) => {
        switch (status) {
            case 'Pending Verification': return 'bg-yellow-100 text-yellow-800'
            case 'Verified': return 'bg-green-100 text-green-800'
            case 'Not Available': return 'bg-red-100 text-red-800'
            case 'Rejected': return 'bg-red-100 text-red-800'
            default: return 'bg-gray-100 text-gray-800'
        }
    }

    if (loading) {
        return (
            <AppLayout title="Procurement Verification" auth={auth}>
                <div className="flex items-center justify-center h-64">
                    <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600"></div>
                </div>
            </AppLayout>
        )
    }

    return (
        <AppLayout title="Procurement Verification" auth={auth}>
            <div className="space-y-6">
                {/* Header */}
                <div>
                    <h1 className="text-2xl font-bold text-gray-900">Procurement Verification</h1>
                    <p className="text-gray-600 mt-1">Verify market availability and set final pricing for requests.</p>
                </div>

                {/* Stats */}
                <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div className="bg-white rounded-lg shadow-sm p-4">
                        <div className="flex items-center">
                            <div className="p-2 bg-yellow-100 rounded-lg">
                                <span className="text-2xl">⏳</span>
                            </div>
                            <div className="ml-4">
                                <p className="text-sm font-medium text-gray-600">Pending Verification</p>
                                <p className="text-2xl font-bold text-gray-900">
                                    {requests.filter(r => r.procurement_status === 'Pending Verification').length}
                                </p>
                            </div>
                        </div>
                    </div>

                    <div className="bg-white rounded-lg shadow-sm p-4">
                        <div className="flex items-center">
                            <div className="p-2 bg-green-100 rounded-lg">
                                <span className="text-2xl">✅</span>
                            </div>
                            <div className="ml-4">
                                <p className="text-sm font-medium text-gray-600">Verified Today</p>
                                <p className="text-2xl font-bold text-gray-900">
                                    {requests.filter(r => r.procurement_status === 'Verified').length}
                                </p>
                            </div>
                        </div>
                    </div>

                    <div className="bg-white rounded-lg shadow-sm p-4">
                        <div className="flex items-center">
                            <div className="p-2 bg-red-100 rounded-lg">
                                <span className="text-2xl">❌</span>
                            </div>
                            <div className="ml-4">
                                <p className="text-sm font-medium text-gray-600">Not Available</p>
                                <p className="text-2xl font-bold text-gray-900">
                                    {requests.filter(r => r.procurement_status === 'Not Available').length}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                {/* Requests List */}
                <div className="bg-white rounded-lg shadow-sm">
                    <div className="px-6 py-4 border-b border-gray-200">
                        <h3 className="text-lg font-medium text-gray-900">Pending Verification Requests</h3>
                    </div>
                    <div className="p-6">
                        {requests.length === 0 ? (
                            <div className="text-center py-8">
                                <div className="text-gray-400 text-6xl mb-4">📋</div>
                                <h3 className="text-lg font-medium text-gray-900 mb-2">No pending verifications</h3>
                                <p className="text-gray-500">All requests have been processed.</p>
                            </div>
                        ) : (
                            <div className="space-y-4">
                                {requests.map((request) => (
                                    <div key={request.id} className="border border-gray-200 rounded-lg p-4 hover:bg-gray-50">
                                        <div className="flex items-center justify-between">
                                            <div className="flex-1">
                                                <div className="flex items-center space-x-4">
                                                    <div>
                                                        <h4 className="text-lg font-medium text-gray-900">{request.item}</h4>
                                                        <p className="text-sm text-gray-600">{request.description}</p>
                                                    </div>
                                                </div>
                                                <div className="mt-2 flex items-center space-x-4 text-sm text-gray-500">
                                                    <span>Employee: {request.employee?.full_name || `User Deleted (ID: ${request.employee_id})`}</span>
                                                    <span>Department: {request.employee?.department?.name || 'Not Available'}</span>
                                                    <span>Amount: {request.amount.toLocaleString()} AFN</span>
                                                    <span>Submitted: {new Date(request.created_at).toLocaleDateString()}</span>
                                                </div>
                                            </div>
                                            <div className="flex items-center space-x-3">
                                                <span className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${getStatusColor(request.procurement_status)}`}>
                                                    {request.procurement_status}
                                                </span>
                                                {request.procurement_status === 'Pending Verification' && (
                                                    <div className="flex space-x-2">
                                                        <button
                                                            onClick={() => handleVerification(request, 'Verified')}
                                                            className="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 transition-colors"
                                                        >
                                                            Verify
                                                        </button>
                                                        <button
                                                            onClick={() => handleVerification(request, 'Not Available')}
                                                            className="bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700 transition-colors"
                                                        >
                                                            Reject
                                                        </button>
                                                    </div>
                                                )}
                                            </div>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        )}
                    </div>
                </div>

                {/* Verification Modal */}
                {showModal && selectedRequest && (
                    <div className="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
                        <div className="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
                            <div className="mt-3">
                                <h3 className="text-lg font-medium text-gray-900 mb-4">
                                    {verificationData.status === 'Verified' ? 'Verify' : 'Reject'} Request: {selectedRequest.item}
                                </h3>

                                <div className="space-y-4">
                                    <div>
                                        <label className="block text-sm font-medium text-gray-700 mb-2">
                                            Verification Status
                                        </label>
                                        <div className="flex items-center space-x-4">
                                            <span className={`inline-flex items-center px-3 py-2 rounded-lg text-sm font-medium ${
                                                verificationData.status === 'Verified'
                                                    ? 'bg-green-100 text-green-800'
                                                    : 'bg-red-100 text-red-800'
                                            }`}>
                                                {verificationData.status === 'Verified' ? '✓ Verified' : '✗ Rejected'}
                                            </span>
                                            <span className="text-sm text-gray-500">
                                                {verificationData.status === 'Verified'
                                                    ? 'Item is available in market'
                                                    : 'Item is not available or rejected'
                                                }
                                            </span>
                                        </div>
                                    </div>

                                    {verificationData.status === 'Verified' && (
                                        <div>
                                            <label className="block text-sm font-medium text-gray-700 mb-2">
                                                Final Amount (AFN) *
                                            </label>
                                            <input
                                                type="number"
                                                step="0.01"
                                                min="0"
                                                value={verificationData.final_price}
                                                onChange={(e) => setVerificationData(prev => ({ ...prev, final_price: e.target.value }))}
                                                className="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                                                placeholder="Enter final negotiated price"
                                                required
                                            />
                                            <p className="text-xs text-gray-500 mt-1">
                                                Original amount: {selectedRequest.amount.toLocaleString()} AFN
                                            </p>
                                        </div>
                                    )}

                                    <div>
                                        <label className="block text-sm font-medium text-gray-700 mb-2">
                                            {verificationData.status === 'Verified' ? 'Verification Notes' : 'Rejection Reason'} *
                                        </label>
                                        <textarea
                                            value={verificationData.notes}
                                            onChange={(e) => setVerificationData(prev => ({ ...prev, notes: e.target.value }))}
                                            className="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                                            rows="3"
                                            placeholder={verificationData.status === 'Verified'
                                                ? 'Add verification details, supplier info, etc...'
                                                : 'Explain why this item is not available or rejected...'
                                            }
                                            required
                                        />
                                    </div>
                                </div>

                                <div className="flex justify-end space-x-3 mt-6">
                                    <button
                                        onClick={() => setShowModal(false)}
                                        className="px-4 py-2 text-gray-700 bg-gray-200 rounded-lg hover:bg-gray-300 transition-colors"
                                        disabled={isProcessing}
                                    >
                                        Cancel
                                    </button>
                                    <button
                                        onClick={handleSubmitVerification}
                                        disabled={isProcessing || !verificationData.notes.trim() || (verificationData.status === 'Verified' && !verificationData.final_price)}
                                        className={`px-4 py-2 rounded-lg transition-colors disabled:opacity-50 ${
                                            verificationData.status === 'Verified'
                                                ? 'bg-green-600 text-white hover:bg-green-700'
                                                : 'bg-red-600 text-white hover:bg-red-700'
                                        }`}
                                    >
                                        {isProcessing
                                            ? 'Processing...'
                                            : verificationData.status === 'Verified'
                                                ? 'Submit Verification'
                                                : 'Submit Rejection'
                                        }
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                )}
            </div>

            {/* Alert Modal */}
            <AlertModal
                isOpen={showAlert}
                onClose={() => setShowAlert(false)}
                title={alertType === 'success' ? 'Success' : alertType === 'error' ? 'Error' : 'Information'}
                message={alertMessage}
                type={alertType}
                buttonText="OK"
                autoClose={alertType === 'success'}
                autoCloseDelay={3000}
            />
        </AppLayout>
    )
}
