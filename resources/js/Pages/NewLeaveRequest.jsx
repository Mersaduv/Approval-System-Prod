import { Head, Link, router } from '@inertiajs/react'
import AppLayout from '../Layouts/AppLayout'
import { useState, useEffect } from 'react'
import axios from 'axios'

export default function NewLeaveRequest({ auth }) {
    const [formData, setFormData] = useState({
        reason: '',
        start_date: '',
        end_date: '',
    })
    const [commonReasons, setCommonReasons] = useState([])
    const [loading, setLoading] = useState(false)
    const [errors, setErrors] = useState({})
    const [totalDays, setTotalDays] = useState(0)

    useEffect(() => {
        fetchCommonReasons()
    }, [])

    useEffect(() => {
        calculateTotalDays()
    }, [formData.start_date, formData.end_date])

    const fetchCommonReasons = async () => {
        try {
            const response = await axios.get('/api/leave-requests/common-reasons')
            if (response.data.success) {
                setCommonReasons(response.data.data)
            }
        } catch (error) {
            console.error('Error fetching common reasons:', error)
        }
    }

    const calculateTotalDays = () => {
        if (formData.start_date && formData.end_date) {
            const start = new Date(formData.start_date)
            const end = new Date(formData.end_date)
            const diffTime = Math.abs(end - start)
            const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24)) + 1 // +1 to include both start and end dates
            setTotalDays(diffDays)
        } else {
            setTotalDays(0)
        }
    }

    const handleInputChange = (e) => {
        const { name, value } = e.target
        setFormData(prev => ({
            ...prev,
            [name]: value
        }))

        // Clear error for this field
        if (errors[name]) {
            setErrors(prev => ({
                ...prev,
                [name]: null
            }))
        }
    }

    const handleSubmit = async (e) => {
        e.preventDefault()
        setLoading(true)
        setErrors({})

        try {
            const response = await axios.post('/api/leave-requests', formData)

            if (response.data.success) {
                // Redirect to leave requests page with success message
                router.visit('/leave-requests', {
                    onSuccess: () => {
                        // You can add a success notification here
                        console.log('Leave request submitted successfully')
                    }
                })
            }
        } catch (error) {
            if (error.response?.status === 422) {
                setErrors(error.response.data.errors || {})
            } else {
                console.error('Error submitting leave request:', error)
                setErrors({
                    general: 'An error occurred while submitting your leave request. Please try again.'
                })
            }
        } finally {
            setLoading(false)
        }
    }

    const getTodayDate = () => {
        const today = new Date()
        return today.toISOString().split('T')[0]
    }

    return (
        <AppLayout title="New Leave Request" auth={auth}>
            <div className="max-w-2xl mx-auto">
                <div className="space-y-6">
                    {/* Header */}
                    <div>
                        <div className="flex items-center gap-4 mb-4">
                            <Link
                                href="/leave-requests"
                                className="text-gray-400 hover:text-gray-600"
                            >
                                <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 19l-7-7 7-7" />
                                </svg>
                            </Link>
                            <h1 className="text-2xl font-bold text-gray-900">New Leave Request</h1>
                        </div>
                        <p className="text-gray-600">Submit a new leave request for approval.</p>
                    </div>

                    {/* Form */}
                    <div className="bg-white shadow-sm rounded-lg">
                        <form onSubmit={handleSubmit} className="p-6 space-y-6">
                            {/* General Error */}
                            {errors.general && (
                                <div className="bg-red-50 border border-red-200 rounded-md p-4">
                                    <div className="flex">
                                        <div className="flex-shrink-0">
                                            <svg className="h-5 w-5 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                            </svg>
                                        </div>
                                        <div className="ml-3">
                                            <p className="text-sm text-red-800">{errors.general}</p>
                                        </div>
                                    </div>
                                </div>
                            )}

                            {/* Reason */}
                            <div>
                                <label htmlFor="reason" className="block text-sm font-medium text-gray-700 mb-2">
                                    Reason for Leave *
                                </label>
                                <textarea
                                    id="reason"
                                    name="reason"
                                    value={formData.reason}
                                    onChange={handleInputChange}
                                    rows={4}
                                    placeholder="Please provide a detailed reason for your leave request..."
                                    className={`block w-full px-3 py-2 border rounded-md shadow-sm focus:outline-none focus:ring-1 text-sm ${
                                        errors.reason
                                            ? 'border-red-300 focus:ring-red-500 focus:border-red-500'
                                            : 'border-gray-300 focus:ring-blue-500 focus:border-blue-500'
                                    }`}
                                    required
                                />
                                {errors.reason && (
                                    <p className="mt-1 text-sm text-red-600">{errors.reason[0]}</p>
                                )}

                                {/* Common Reasons Suggestions */}
                                {commonReasons.length > 0 && (
                                    <div className="mt-2">
                                        <p className="text-xs text-gray-500 mb-2">Common reasons:</p>
                                        <div className="flex flex-wrap gap-1">
                                            {commonReasons.map((reason, index) => (
                                                <button
                                                    key={index}
                                                    type="button"
                                                    onClick={() => setFormData(prev => ({ ...prev, reason }))}
                                                    className="inline-flex items-center px-2 py-1 rounded-md text-xs font-medium bg-gray-100 text-gray-800 hover:bg-gray-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
                                                >
                                                    {reason}
                                                </button>
                                            ))}
                                        </div>
                                    </div>
                                )}
                            </div>

                            {/* Date Range */}
                            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label htmlFor="start_date" className="block text-sm font-medium text-gray-700 mb-2">
                                        Start Date *
                                    </label>
                                    <input
                                        type="date"
                                        id="start_date"
                                        name="start_date"
                                        value={formData.start_date}
                                        onChange={handleInputChange}
                                        min={getTodayDate()}
                                        className={`block w-full px-3 py-2 border rounded-md shadow-sm focus:outline-none focus:ring-1 text-sm ${
                                            errors.start_date
                                                ? 'border-red-300 focus:ring-red-500 focus:border-red-500'
                                                : 'border-gray-300 focus:ring-blue-500 focus:border-blue-500'
                                        }`}
                                        required
                                    />
                                    {errors.start_date && (
                                        <p className="mt-1 text-sm text-red-600">{errors.start_date[0]}</p>
                                    )}
                                </div>

                                <div>
                                    <label htmlFor="end_date" className="block text-sm font-medium text-gray-700 mb-2">
                                        End Date *
                                    </label>
                                    <input
                                        type="date"
                                        id="end_date"
                                        name="end_date"
                                        value={formData.end_date}
                                        onChange={handleInputChange}
                                        min={formData.start_date || getTodayDate()}
                                        className={`block w-full px-3 py-2 border rounded-md shadow-sm focus:outline-none focus:ring-1 text-sm ${
                                            errors.end_date
                                                ? 'border-red-300 focus:ring-red-500 focus:border-red-500'
                                                : 'border-gray-300 focus:ring-blue-500 focus:border-blue-500'
                                        }`}
                                        required
                                    />
                                    {errors.end_date && (
                                        <p className="mt-1 text-sm text-red-600">{errors.end_date[0]}</p>
                                    )}
                                </div>
                            </div>

                            {/* Total Days Display */}
                            {totalDays > 0 && (
                                <div className="bg-blue-50 border border-blue-200 rounded-md p-4">
                                    <div className="flex items-center">
                                        <div className="flex-shrink-0">
                                            <svg className="h-5 w-5 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                            </svg>
                                        </div>
                                        <div className="ml-3">
                                            <p className="text-sm text-blue-800">
                                                <strong>Total Days:</strong> {totalDays} day{totalDays !== 1 ? 's' : ''}
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            )}


                            {/* Submit Buttons */}
                            <div className="flex items-center justify-end space-x-4 pt-4 border-t border-gray-200">
                                <Link
                                    href="/leave-requests"
                                    className="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
                                >
                                    Cancel
                                </Link>
                                <button
                                    type="submit"
                                    disabled={loading}
                                    className="px-4 py-2 text-sm font-medium text-white bg-blue-600 border border-transparent rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2"
                                >
                                    {loading && (
                                        <svg className="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
                                            <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"></circle>
                                            <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                        </svg>
                                    )}
                                    {loading ? 'Submitting...' : 'Submit Leave Request'}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </AppLayout>
    )
}
