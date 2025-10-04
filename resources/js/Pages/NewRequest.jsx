import { Head, Link, router } from '@inertiajs/react'
import AppLayout from '../Layouts/AppLayout'
import { useState, useEffect } from 'react'
import axios from 'axios'
import AlertModal from '../Components/AlertModal'

export default function NewRequest({ auth }) {
    const [formData, setFormData] = useState({
        item: '',
        description: '',
        amount: '',
        priority: 'normal'
    })
    const [errors, setErrors] = useState({})
    const [isSubmitting, setIsSubmitting] = useState(false)
    const [loading, setLoading] = useState(false)
    const [showAlert, setShowAlert] = useState(false)
    const [alertMessage, setAlertMessage] = useState('')
    const [alertType, setAlertType] = useState('info')

    const showAlertMessage = (message, type = 'info') => {
        setAlertMessage(message)
        setAlertType(type)
        setShowAlert(true)
    }

    const handleChange = (e) => {
        const { name, value } = e.target
        setFormData(prev => ({
            ...prev,
            [name]: value
        }))
        // Clear error when user starts typing
        if (errors[name]) {
            setErrors(prev => ({
                ...prev,
                [name]: ''
            }))
        }
    }

    const validateForm = () => {
        const newErrors = {}

        if (!formData.item.trim()) {
            newErrors.item = 'Item name is required'
        }

        if (!formData.description.trim()) {
            newErrors.description = 'Description is required'
        }

        if (!formData.amount || isNaN(formData.amount) || parseFloat(formData.amount) <= 0) {
            newErrors.amount = 'Valid amount is required'
        }

        setErrors(newErrors)
        return Object.keys(newErrors).length === 0
    }

    const handleSubmit = async (e) => {
        e.preventDefault()

        if (!validateForm()) {
            return
        }

        setIsSubmitting(true)

        try {
            const response = await axios.post('/api/requests', {
                item: formData.item,
                description: formData.description,
                amount: parseFloat(formData.amount)
            })

            if (response.data.success) {
                // Reset form
                setFormData({
                    item: '',
                    description: '',
                    amount: '',
                    priority: 'normal'
                })

                // Redirect to requests page
                router.visit('/requests')
            } else {
                throw new Error(response.data.message || 'Failed to submit request')
            }

        } catch (error) {
            console.error('Error submitting request:', error)
            if (error.response?.data?.errors) {
                setErrors(error.response.data.errors)
            } else {
                showAlertMessage('Error submitting request: ' + (error.response?.data?.message || error.message), 'error')
            }
        } finally {
            setIsSubmitting(false)
        }
    }


    return (
        <AppLayout title="New Request" auth={auth}>
            <div className="max-w-2xl mx-auto">
                {/* Header */}
                <div className="mb-6 lg:mb-8">
                    <div className="flex items-center space-x-3 lg:space-x-4 mb-4">
                        <Link
                            href="/requests"
                            className="text-gray-400 hover:text-gray-600"
                        >
                            <svg className="w-5 h-5 lg:w-6 lg:h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 19l-7-7 7-7" />
                            </svg>
                        </Link>
                        <h1 className="text-2xl lg:text-3xl font-bold text-gray-900">New Request</h1>
                    </div>
                    <p className="text-sm lg:text-base text-gray-600">
                        Submit a new approval request for items or services
                    </p>
                </div>

                {/* Form */}
                <div className="bg-white rounded-lg shadow-sm">
                    <form onSubmit={handleSubmit} className="p-4 lg:p-6 space-y-4 lg:space-y-6">
                        {/* Item Name */}
                        <div>
                            <label htmlFor="item" className="block text-sm font-medium text-gray-700 mb-2">
                                Item/Service Name *
                            </label>
                            <input
                                type="text"
                                id="item"
                                name="item"
                                value={formData.item}
                                onChange={handleChange}
                                className={`w-full px-3 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 ${
                                    errors.item ? 'border-red-300' : 'border-gray-300'
                                }`}
                                placeholder="e.g., Office Supplies, Laptop Computer, Conference Room Equipment"
                            />
                            {errors.item && (
                                <p className="mt-1 text-sm text-red-600">{errors.item}</p>
                            )}
                        </div>

                        {/* Description */}
                        <div>
                            <label htmlFor="description" className="block text-sm font-medium text-gray-700 mb-2">
                                Description *
                            </label>
                            <textarea
                                id="description"
                                name="description"
                                rows={4}
                                value={formData.description}
                                onChange={handleChange}
                                className={`w-full px-3 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 ${
                                    errors.description ? 'border-red-300' : 'border-gray-300'
                                }`}
                                placeholder="Provide detailed description of what you need and why..."
                            />
                            {errors.description && (
                                <p className="mt-1 text-sm text-red-600">{errors.description}</p>
                            )}
                        </div>

                        {/* Amount */}
                        <div>
                            <label htmlFor="amount" className="block text-sm font-medium text-gray-700 mb-2">
                                Amount (AFN) *
                            </label>
                            <input
                                type="number"
                                id="amount"
                                name="amount"
                                value={formData.amount}
                                onChange={handleChange}
                                min="0"
                                step="0.01"
                                className={`w-full px-3 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 ${
                                    errors.amount ? 'border-red-300' : 'border-gray-300'
                                }`}
                                placeholder="0.00"
                            />
                            {errors.amount && (
                                <p className="mt-1 text-sm text-red-600">{errors.amount}</p>
                            )}
                        </div>

                        {/* Priority */}
                        <div>
                            <label htmlFor="priority" className="block text-sm font-medium text-gray-700 mb-2">
                                Priority
                            </label>
                            <select
                                id="priority"
                                name="priority"
                                value={formData.priority}
                                onChange={handleChange}
                                className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                            >
                                <option value="low">Low - Can wait 1-2 weeks</option>
                                <option value="normal">Normal - Standard processing time</option>
                                <option value="high">High - Urgent, needed within 3 days</option>
                                <option value="critical">Critical - Emergency, needed immediately</option>
                            </select>
                        </div>

                        {/* Submit Buttons */}
                        <div className="flex justify-end space-x-4 pt-6 border-t border-gray-200">
                            <Link
                                href="/requests"
                                className="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500"
                            >
                                Cancel
                            </Link>
                            <button
                                type="submit"
                                disabled={isSubmitting}
                                className="px-6 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 disabled:opacity-50 disabled:cursor-not-allowed flex items-center"
                            >
                                {isSubmitting ? (
                                    <>
                                        <svg className="animate-spin -ml-1 mr-3 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                            <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"></circle>
                                            <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                        </svg>
                                        Submitting...
                                    </>
                                ) : (
                                    'Submit Request'
                                )}
                            </button>
                        </div>
                    </form>
                </div>
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
