import { Head, Link } from '@inertiajs/react'
import AppLayout from '../Layouts/AppLayout'
import { useState, useEffect } from 'react'

export default function WorkflowDemo({ auth }) {
    const [currentStep, setCurrentStep] = useState(0)
    const [isRunning, setIsRunning] = useState(false)
    const [workflowData, setWorkflowData] = useState({
        request: null,
        steps: [],
        notifications: [],
        auditLog: []
    })

    const workflowSteps = [
        {
            id: 1,
            title: 'Employee Submits Request',
            description: 'John Doe from IT department submits a request for a new laptop computer worth 8,500 AFN',
            action: 'Submit Request',
            status: 'pending',
            user: 'John Doe (Employee)',
            department: 'IT',
            details: {
                item: 'Dell Latitude 5520 Laptop',
                description: 'High-performance laptop for software development work',
                amount: 8500,
                priority: 'Normal'
            }
        },
        {
            id: 2,
            title: 'Manager Approval Required',
            description: 'Request requires approval from IT Manager (Sarah Wilson)',
            action: 'Manager Reviews',
            status: 'pending',
            user: 'Sarah Wilson (IT Manager)',
            department: 'IT',
            details: {
                approvalRequired: true,
                role: 'Manager',
                department: 'IT'
            }
        },
        {
            id: 3,
            title: 'Admin Approval Required',
            description: 'Amount exceeds 5,000 AFN threshold, Admin approval is required',
            action: 'Admin Reviews',
            status: 'pending',
            user: 'David Brown (Admin)',
            department: 'Administration',
            details: {
                approvalRequired: true,
                role: 'Admin',
                reason: 'Amount exceeds 5,000 AFN threshold'
            }
        },
        {
            id: 4,
            title: 'Forward to Procurement',
            description: 'All approvals complete, request forwarded to procurement team',
            action: 'Procurement Processing',
            status: 'pending',
            user: 'Lisa Chen (Procurement)',
            department: 'Procurement',
            details: {
                status: 'Ordered',
                nextStep: 'Procurement will order and track delivery'
            }
        },
        {
            id: 5,
            title: 'Item Delivered',
            description: 'Laptop has been delivered and is ready for use',
            action: 'Delivery Confirmed',
            status: 'pending',
            user: 'Lisa Chen (Procurement)',
            department: 'Procurement',
            details: {
                status: 'Delivered',
                finalCost: 8500,
                deliveryDate: '2024-01-20'
            }
        }
    ]

    const runWorkflow = async () => {
        setIsRunning(true)
        setCurrentStep(0)

        // Simulate workflow execution
        for (let i = 0; i < workflowSteps.length; i++) {
            setCurrentStep(i)

            // Simulate processing time
            await new Promise(resolve => setTimeout(resolve, 2000))

            // Update workflow data
            setWorkflowData(prev => ({
                ...prev,
                request: i === 0 ? {
                    id: 1001,
                    item: workflowSteps[i].details.item,
                    description: workflowSteps[i].details.description,
                    amount: workflowSteps[i].details.amount,
                    status: i === workflowSteps.length - 1 ? 'Delivered' : 'Pending',
                    employee: 'John Doe',
                    department: 'IT',
                    created_at: new Date().toISOString()
                } : prev.request,
                steps: [...prev.steps, {
                    ...workflowSteps[i],
                    status: 'completed',
                    completedAt: new Date().toISOString()
                }],
                notifications: [...prev.notifications, {
                    id: i + 1,
                    type: 'info',
                    message: `${workflowSteps[i].user} ${workflowSteps[i].action.toLowerCase()}`,
                    timestamp: new Date().toISOString()
                }],
                auditLog: [...prev.auditLog, {
                    id: i + 1,
                    user: workflowSteps[i].user,
                    action: workflowSteps[i].action,
                    timestamp: new Date().toISOString(),
                    details: workflowSteps[i].details
                }]
            }))
        }

        setIsRunning(false)
    }

    const resetWorkflow = () => {
        setCurrentStep(0)
        setIsRunning(false)
        setWorkflowData({
            request: null,
            steps: [],
            notifications: [],
            auditLog: []
        })
    }

    const getStepStatus = (stepId) => {
        if (currentStep > stepId) return 'completed'
        if (currentStep === stepId) return 'current'
        return 'pending'
    }

    const getStatusColor = (status) => {
        switch (status) {
            case 'completed': return 'bg-green-500'
            case 'current': return 'bg-blue-500'
            default: return 'bg-gray-300'
        }
    }

    const getStatusIcon = (status) => {
        switch (status) {
            case 'completed': return '✅'
            case 'current': return '⏳'
            default: return '⭕'
        }
    }

    return (
        <AppLayout title="Workflow Demo" auth={auth}>
            <div className="max-w-6xl mx-auto">
                {/* Header */}
                <div className="mb-8">
                    <h1 className="text-3xl font-bold text-gray-900">Workflow Demonstration</h1>
                    <p className="text-gray-600 mt-2">
                        See how the approval workflow system processes a request from start to finish
                    </p>
                </div>

                {/* Controls */}
                <div className="bg-white rounded-lg shadow-sm p-6 mb-8">
                    <div className="flex items-center justify-between">
                        <div>
                            <h3 className="text-lg font-medium text-gray-900">Example Scenario</h3>
                            <p className="text-sm text-gray-500">
                                IT Employee requests a laptop computer worth 8,500 AFN
                            </p>
                        </div>
                        <div className="flex space-x-4">
                            <button
                                onClick={runWorkflow}
                                disabled={isRunning}
                                className="bg-blue-600 hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed text-white px-6 py-2 rounded-md font-medium flex items-center"
                            >
                                {isRunning ? (
                                    <>
                                        <svg className="animate-spin -ml-1 mr-3 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                            <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"></circle>
                                            <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                        </svg>
                                        Running...
                                    </>
                                ) : (
                                    'Start Demo'
                                )}
                            </button>
                            <button
                                onClick={resetWorkflow}
                                disabled={isRunning}
                                className="bg-gray-600 hover:bg-gray-700 disabled:opacity-50 disabled:cursor-not-allowed text-white px-6 py-2 rounded-md font-medium"
                            >
                                Reset
                            </button>
                        </div>
                    </div>
                </div>

                <div className="grid grid-cols-1 lg:grid-cols-3 gap-8">
                    {/* Workflow Steps */}
                    <div className="lg:col-span-2">
                        <div className="bg-white rounded-lg shadow-sm">
                            <div className="px-6 py-4 border-b border-gray-200">
                                <h3 className="text-lg font-medium text-gray-900">Workflow Steps</h3>
                            </div>
                            <div className="p-6">
                                <div className="space-y-6">
                                    {workflowSteps.map((step, index) => {
                                        const status = getStepStatus(index)
                                        return (
                                            <div key={step.id} className="flex items-start space-x-4">
                                                <div className={`flex-shrink-0 w-8 h-8 rounded-full flex items-center justify-center text-white text-sm font-medium ${getStatusColor(status)}`}>
                                                    {getStatusIcon(status)}
                                                </div>
                                                <div className="flex-1 min-w-0">
                                                    <div className="flex items-center justify-between">
                                                        <h4 className="text-sm font-medium text-gray-900">{step.title}</h4>
                                                        <span className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${
                                                            status === 'completed' ? 'bg-green-100 text-green-800' :
                                                            status === 'current' ? 'bg-blue-100 text-blue-800' :
                                                            'bg-gray-100 text-gray-800'
                                                        }`}>
                                                            {status === 'completed' ? 'Completed' :
                                                             status === 'current' ? 'In Progress' : 'Pending'}
                                                        </span>
                                                    </div>
                                                    <p className="mt-1 text-sm text-gray-600">{step.description}</p>
                                                    <div className="mt-2 text-xs text-gray-500">
                                                        <p><strong>User:</strong> {step.user}</p>
                                                        <p><strong>Department:</strong> {step.department}</p>
                                                        {step.details && (
                                                            <div className="mt-1">
                                                                {Object.entries(step.details).map(([key, value]) => (
                                                                    <p key={key}>
                                                                        <strong>{key.replace(/([A-Z])/g, ' $1').replace(/^./, str => str.toUpperCase())}:</strong> {value}
                                                                    </p>
                                                                ))}
                                                            </div>
                                                        )}
                                                    </div>
                                                </div>
                                            </div>
                                        )
                                    })}
                                </div>
                            </div>
                        </div>
                    </div>

                    {/* Request Details & Notifications */}
                    <div className="space-y-6">
                        {/* Request Details */}
                        {workflowData.request && (
                            <div className="bg-white rounded-lg shadow-sm">
                                <div className="px-6 py-4 border-b border-gray-200">
                                    <h3 className="text-lg font-medium text-gray-900">Request Details</h3>
                                </div>
                                <div className="p-6">
                                    <div className="space-y-3">
                                        <div>
                                            <p className="text-sm font-medium text-gray-600">Item</p>
                                            <p className="text-sm text-gray-900">{workflowData.request.item}</p>
                                        </div>
                                        <div>
                                            <p className="text-sm font-medium text-gray-600">Description</p>
                                            <p className="text-sm text-gray-900">{workflowData.request.description}</p>
                                        </div>
                                        <div>
                                            <p className="text-sm font-medium text-gray-600">Amount</p>
                                            <p className="text-sm text-gray-900">{workflowData.request.amount.toLocaleString()} AFN</p>
                                        </div>
                                        <div>
                                            <p className="text-sm font-medium text-gray-600">Status</p>
                                            <span className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${
                                                workflowData.request.status === 'Delivered' ? 'bg-green-100 text-green-800' :
                                                workflowData.request.status === 'Approved' ? 'bg-blue-100 text-blue-800' :
                                                'bg-yellow-100 text-yellow-800'
                                            }`}>
                                                {workflowData.request.status}
                                            </span>
                                        </div>
                                        <div>
                                            <p className="text-sm font-medium text-gray-600">Employee</p>
                                            <p className="text-sm text-gray-900">{workflowData.request.employee}</p>
                                        </div>
                                        <div>
                                            <p className="text-sm font-medium text-gray-600">Department</p>
                                            <p className="text-sm text-gray-900">{workflowData.request.department}</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        )}

                        {/* Notifications */}
                        {workflowData.notifications.length > 0 && (
                            <div className="bg-white rounded-lg shadow-sm">
                                <div className="px-6 py-4 border-b border-gray-200">
                                    <h3 className="text-lg font-medium text-gray-900">Notifications</h3>
                                </div>
                                <div className="p-6">
                                    <div className="space-y-3">
                                        {workflowData.notifications.map((notification) => (
                                            <div key={notification.id} className="flex items-start space-x-3">
                                                <div className="flex-shrink-0">
                                                    <span className="text-lg">🔔</span>
                                                </div>
                                                <div className="flex-1 min-w-0">
                                                    <p className="text-sm text-gray-900">{notification.message}</p>
                                                    <p className="text-xs text-gray-500">
                                                        {new Date(notification.timestamp).toLocaleTimeString()}
                                                    </p>
                                                </div>
                                            </div>
                                        ))}
                                    </div>
                                </div>
                            </div>
                        )}

                        {/* Audit Log */}
                        {workflowData.auditLog.length > 0 && (
                            <div className="bg-white rounded-lg shadow-sm">
                                <div className="px-6 py-4 border-b border-gray-200">
                                    <h3 className="text-lg font-medium text-gray-900">Audit Log</h3>
                                </div>
                                <div className="p-6">
                                    <div className="space-y-3">
                                        {workflowData.auditLog.map((log) => (
                                            <div key={log.id} className="flex items-start space-x-3">
                                                <div className="flex-shrink-0">
                                                    <span className="text-lg">📝</span>
                                                </div>
                                                <div className="flex-1 min-w-0">
                                                    <p className="text-sm text-gray-900">
                                                        <strong>{log.user}</strong> {log.action}
                                                    </p>
                                                    <p className="text-xs text-gray-500">
                                                        {new Date(log.timestamp).toLocaleTimeString()}
                                                    </p>
                                                </div>
                                            </div>
                                        ))}
                                    </div>
                                </div>
                            </div>
                        )}
                    </div>
                </div>

                {/* Workflow Summary */}
                <div className="mt-8 bg-white rounded-lg shadow-sm p-6">
                    <h3 className="text-lg font-medium text-gray-900 mb-4">Workflow Summary</h3>
                    <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <div className="text-center">
                            <div className="text-2xl font-bold text-blue-600">{workflowSteps.length}</div>
                            <div className="text-sm text-gray-600">Total Steps</div>
                        </div>
                        <div className="text-center">
                            <div className="text-2xl font-bold text-green-600">{workflowData.steps.length}</div>
                            <div className="text-sm text-gray-600">Completed Steps</div>
                        </div>
                        <div className="text-center">
                            <div className="text-2xl font-bold text-purple-600">{workflowData.notifications.length}</div>
                            <div className="text-sm text-gray-600">Notifications Sent</div>
                        </div>
                    </div>
                </div>
            </div>
        </AppLayout>
    )
}
