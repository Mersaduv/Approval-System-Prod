import { Head } from '@inertiajs/react'
import AppLayout from '../Layouts/AppLayout'
import { useState, useEffect } from 'react'
import axios from 'axios'
import AlertModal from '../Components/AlertModal'
import ConfirmationModal from '../Components/ConfirmationModal'
import {
    DndContext,
    closestCenter,
    KeyboardSensor,
    PointerSensor,
    useSensor,
    useSensors,
} from '@dnd-kit/core'
import {
    arrayMove,
    SortableContext,
    sortableKeyboardCoordinates,
    verticalListSortingStrategy,
} from '@dnd-kit/sortable'
import {
    useSortable,
} from '@dnd-kit/sortable'
import { CSS } from '@dnd-kit/utilities'

// Sortable Step Component
function SortableStep({ step, onEdit, onDelete, getEntityName }) {
    const {
        attributes,
        listeners,
        setNodeRef,
        transform,
        transition,
        isDragging,
    } = useSortable({ id: step.id })

    const style = {
        transform: CSS.Transform.toString(transform),
        transition,
        opacity: isDragging ? 0.5 : 1,
    }

    return (
        <div
            ref={setNodeRef}
            style={style}
            className={`border border-gray-200 rounded-lg p-4 bg-white sortable-item ${isDragging ? 'dragging' : ''}`}
        >
            <div className="flex justify-between items-start">
                <div className="flex-1">
                    <div className="flex items-center gap-3 mb-2">
                        <div
                            {...attributes}
                            {...listeners}
                            className="drag-handle p-2 text-gray-400 hover:text-gray-600 rounded-md hover:bg-gray-100 transition-colors"
                            title="Drag to reorder steps"
                        >
                            <svg className="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M11 18c0 1.1-.9 2-2 2s-2-.9-2-2 .9-2 2-2 2 .9 2 2zm-2-8c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2zm0-6c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2zm6 4c1.1 0 2-.9 2-2s-.9-2-2-2-2 .9-2 2 .9 2 2 2zm0 2c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2zm0 6c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2z"/>
                            </svg>
                        </div>
                        <span className="bg-blue-100 text-blue-800 text-sm font-medium px-2.5 py-0.5 rounded">
                            {step.order_index + 1}
                        </span>
                        <h3 className="text-lg font-semibold text-gray-900">
                            {step.name}
                        </h3>
                        <span className={`px-2 py-1 text-xs rounded-full ${
                            step.is_active
                                ? 'bg-green-100 text-green-800'
                                : 'bg-red-100 text-red-800'
                        }`}>
                            {step.is_active ? 'Active' : 'Inactive'}
                        </span>
                        <span className={`px-2 py-1 text-xs rounded-full ${
                            step.step_type === 'approval'
                                ? 'bg-blue-100 text-blue-800'
                                : step.step_type === 'verification'
                                ? 'bg-yellow-100 text-yellow-800'
                                : 'bg-purple-100 text-purple-800'
                        }`}>
                            {step.step_type === 'approval' ? 'Approval' :
                             step.step_type === 'verification' ? 'Verification' : 'Notification'}
                        </span>
                    </div>

                    {step.description && (
                        <p className="text-gray-600 mb-3">{step.description}</p>
                    )}

                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                        <div>
                            <span className="font-medium text-gray-700">Type:</span>
                            <span className="mr-2">{step.step_type}</span>
                        </div>
                        <div>
                            <span className="font-medium text-gray-700">Timeout:</span>
                            <span className="mr-2">
                                {step.timeout_hours ? `${step.timeout_hours} hours` : 'Unlimited'}
                            </span>
                        </div>
                    </div>

                    {step.assignments && step.assignments.length > 0 && (
                        <div className="mt-3">
                            <span className="font-medium text-gray-700 text-sm">Assignments:</span>
                            <div className="flex flex-wrap gap-2 mt-1">
                                {step.assignments.map((assignment, idx) => (
                                    <span
                                        key={idx}
                                        className={`px-2 py-1 rounded text-xs ${
                                            assignment.is_required
                                                ? 'bg-red-100 text-red-700 border border-red-200'
                                                : 'bg-gray-100 text-gray-700'
                                        }`}
                                    >
                                        {getEntityName(assignment)}
                                        {assignment.is_required && (
                                            <span className="ml-1 font-semibold">*</span>
                                        )}
                                    </span>
                                ))}
                            </div>
                        </div>
                    )}
                </div>

                <div className="flex gap-2">
                    <button
                        onClick={() => onEdit(step)}
                        className="text-blue-600 hover:text-blue-800 p-1"
                        title="Edit"
                    >
                        <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                        </svg>
                    </button>
                    <button
                        onClick={() => onDelete(step)}
                        className="text-red-600 hover:text-red-800 p-1"
                        title="Delete"
                    >
                        <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                        </svg>
                    </button>
                </div>
            </div>
        </div>
    )
}

export default function WorkflowSettings({ auth }) {
    const [steps, setSteps] = useState([])
    const [loading, setLoading] = useState(true)
    const [showModal, setShowModal] = useState(false)
    const [editingStep, setEditingStep] = useState(null)
    const [assignableEntities, setAssignableEntities] = useState({
        users: [],
        roles: [],
        departments: []
    })
    const [showAlert, setShowAlert] = useState(false)
    const [alertMessage, setAlertMessage] = useState('')
    const [alertType, setAlertType] = useState('info')
    const [showConfirm, setShowConfirm] = useState(false)
    const [confirmAction, setConfirmAction] = useState(null)
    const [isReordering, setIsReordering] = useState(false)

    const sensors = useSensors(
        useSensor(PointerSensor),
        useSensor(KeyboardSensor, {
            coordinateGetter: sortableKeyboardCoordinates,
        })
    )

    const [stepForm, setStepForm] = useState({
        name: '',
        description: '',
        order_index: 0,
        is_active: true,
        step_type: 'approval',
        timeout_hours: null, // Unlimited by default
        auto_approve: false,
        conditions: [],
        assignments: []
    })

    const showAlertMessage = (message, type = 'info') => {
        setAlertMessage(message)
        setAlertType(type)
        setShowAlert(true)
    }

    useEffect(() => {
        fetchSteps()
        fetchAssignableEntities()
    }, [])

    // Update steps when assignableEntities are loaded
    useEffect(() => {
        if (assignableEntities.roles.length > 0 && steps.length > 0) {
            // Force re-render to update assignment display
            setSteps([...steps])
        }
    }, [assignableEntities])

    const fetchSteps = async () => {
        try {
            const response = await axios.get('/api/admin/workflow-steps')
            if (response.data.success) {
                setSteps(response.data.data)
            }
        } catch (error) {
            console.error('Error fetching steps:', error)
            showAlertMessage('Error loading workflow steps', 'error')
        } finally {
            setLoading(false)
        }
    }

    const fetchAssignableEntities = async () => {
        try {
            const response = await axios.get('/api/admin/workflow-steps/assignable-entities')
            if (response.data.success) {
                setAssignableEntities(response.data.data)
            }
        } catch (error) {
            console.error('Error fetching assignable entities:', error)
        }
    }

    const handleAddStep = () => {
        setEditingStep(null)
        setStepForm({
            name: '',
            description: '',
            order_index: steps.length,
            is_active: true,
            step_type: 'approval',
            timeout_hours: null, // Unlimited by default
            auto_approve: false,
            conditions: [],
            assignments: [{
                assignment_type: 'admin',
                user_id: '',
                is_required: true
            }]
        })
        setShowModal(true)
    }

    const handleEditStep = (step) => {
        setEditingStep(step)

        // Ensure assignableEntities are loaded before converting assignments
        if (assignableEntities.roles.length === 0) {
            fetchAssignableEntities().then(() => {
                setStepForm({
                    name: step.name,
                    description: step.description || '',
                    order_index: step.order_index,
                    is_active: step.is_active,
                    step_type: step.step_type,
                    timeout_hours: step.timeout_hours,
                    auto_approve: step.auto_approve || false,
                    conditions: step.conditions || [],
                    assignments: (step.assignments || []).map(assignment => convertAssignmentToNewFormat(assignment))
                })
                setShowModal(true)
            })
        } else {
            setStepForm({
                name: step.name,
                description: step.description || '',
                order_index: step.order_index,
                is_active: step.is_active,
                step_type: step.step_type,
                timeout_hours: step.timeout_hours,
                auto_approve: step.auto_approve || false,
                conditions: step.conditions || [],
                assignments: (step.assignments || []).map(assignment => convertAssignmentToNewFormat(assignment))
            })
            setShowModal(true)
        }
    }

    const handleSaveStep = async () => {
        // Validation
        if (!stepForm.name.trim()) {
            showAlertMessage('Step name is required', 'error')
            return
        }

        if (!stepForm.step_type) {
            showAlertMessage('Step type is required', 'error')
            return
        }

        if (!stepForm.auto_approve && (!stepForm.assignments || stepForm.assignments.length === 0)) {
            showAlertMessage('At least one assignment is required when auto approve is disabled', 'error')
            return
        }

        // Validate assignments only if auto_approve is false
        if (!stepForm.auto_approve) {
            for (let i = 0; i < stepForm.assignments.length; i++) {
                const assignment = stepForm.assignments[i]
                if (!assignment.assignment_type) {
                    showAlertMessage(`Assignment ${i + 1}: Assignment type is required`, 'error')
                    return
                }
                if ((assignment.assignment_type === 'user' || assignment.assignment_type === 'finance') && !assignment.user_id) {
                    showAlertMessage(`Assignment ${i + 1}: User selection is required`, 'error')
                    return
                }
            }
        }

        try {
            const url = editingStep
                ? `/api/admin/workflow-steps/${editingStep.id}`
                : '/api/admin/workflow-steps'

            const method = editingStep ? 'put' : 'post'

            const response = await axios[method](url, stepForm)

            if (response.data.success) {
                showAlertMessage(
                    editingStep ? 'Step updated successfully' : 'New step created successfully',
                    'success'
                )
                setShowModal(false)
                fetchSteps()
            }
        } catch (error) {
            console.error('Error saving step:', error)
            showAlertMessage('Error saving step', 'error')
        }
    }

    const handleDeleteStep = (step) => {
        setConfirmAction(() => () => deleteStep(step.id))
        setShowConfirm(true)
    }

    const deleteStep = async (stepId) => {
        try {
            const response = await axios.delete(`/api/admin/workflow-steps/${stepId}`)
            if (response.data.success) {
                showAlertMessage('Step deleted successfully', 'success')
                fetchSteps()
                setShowConfirm(false) // Close the confirmation modal
            }
        } catch (error) {
            console.error('Error deleting step:', error)
            showAlertMessage('Error deleting step', 'error')
            setShowConfirm(false) // Close the confirmation modal even on error
        }
    }

    const handleDragEnd = async (event) => {
        const { active, over } = event

        if (active.id !== over.id) {
            setIsReordering(true)
            const oldIndex = steps.findIndex(step => step.id === active.id)
            const newIndex = steps.findIndex(step => step.id === over.id)

            const newOrder = arrayMove(steps, oldIndex, newIndex)

            // Update order_index for each step based on new position
            const updatedOrder = newOrder.map((step, index) => ({
                ...step,
                order_index: index
            }))

            // Update local state immediately for better UX
            setSteps(updatedOrder)

            try {
                const stepIds = updatedOrder.map(step => step.id)
                const response = await axios.post('/api/admin/workflow-steps/reorder', {
                    step_ids: stepIds
                })

                if (!response.data.success) {
                    // Revert on failure
                    setSteps(steps)
                    showAlertMessage('Error reordering steps', 'error')
                }
            } catch (error) {
                console.error('Error reordering steps:', error)
                // Revert on error
                setSteps(steps)
                showAlertMessage('Error reordering steps', 'error')
            } finally {
                setIsReordering(false)
            }
        }
    }

    const addCondition = () => {
        setStepForm(prev => ({
            ...prev,
            conditions: [...prev.conditions, {
                field: 'amount',
                operator: '>',
                value: 0
            }]
        }))
    }

    const updateCondition = (index, field, value) => {
        setStepForm(prev => ({
            ...prev,
            conditions: prev.conditions.map((condition, i) => {
                if (i === index) {
                    const updatedCondition = { ...condition, [field]: value }

                    // Reset value and operator when field changes
                    if (field === 'field') {
                        if (value === 'amount') {
                            updatedCondition.value = 0
                            updatedCondition.operator = '>'
                        } else if (value === 'status') {
                            updatedCondition.value = ''
                            updatedCondition.operator = '='
                        } else {
                            updatedCondition.value = ''
                            updatedCondition.operator = '='
                        }
                    }

                    return updatedCondition
                }
                return condition
            })
        }))
    }

    const removeCondition = (index) => {
        setStepForm(prev => ({
            ...prev,
            conditions: prev.conditions.filter((_, i) => i !== index)
        }))
    }

    const addAssignment = () => {
        setStepForm(prev => ({
            ...prev,
            assignments: [...prev.assignments, {
                assignment_type: 'admin',
                user_id: '',
                is_required: true
            }]
        }))
    }

    const updateAssignment = (index, field, value) => {
        setStepForm(prev => ({
            ...prev,
            assignments: prev.assignments.map((assignment, i) =>
                i === index ? { ...assignment, [field]: value } : assignment
            )
        }))
    }

    const removeAssignment = (index) => {
        setStepForm(prev => ({
            ...prev,
            assignments: prev.assignments.filter((_, i) => i !== index)
        }))
    }

    const getAssignableOptions = (type) => {
        switch (type) {
            case 'user':
                return assignableEntities.users
            case 'admin':
            case 'manager':
            case 'procurement':
                return assignableEntities.roles.filter(role => role.name === type)
            case 'finance':
                return assignableEntities.users.filter(user => user.department_name === 'Finance')
            default:
                return []
        }
    }

    const convertAssignmentToNewFormat = (assignment) => {
        let assignmentType = 'admin';
        let userId = '';

        if (assignment.assignment_type) {
            // New structure - backend already provides assignment_type and user_id
            assignmentType = assignment.assignment_type;
            userId = assignment.user_id ? String(assignment.user_id) : '';
        } else if (assignment.assignable_type) {
            // Old structure - convert to new
            switch (assignment.assignable_type) {
                case 'App\\Models\\User':
                    assignmentType = 'user';
                    userId = assignment.assignable_id ? String(assignment.assignable_id) : '';
                    break;
                case 'App\\Models\\FinanceAssignment':
                    assignmentType = 'finance';
                    // For FinanceAssignment, the user_id should already be provided by the backend
                    // If not, we need to get it from the assignable relationship
                    userId = assignment.user_id ? String(assignment.user_id) :
                            (assignment.assignable && assignment.assignable.user_id) ? String(assignment.assignable.user_id) : '';
                    break;
                case 'App\\Models\\Role':
                    // Find role name by ID
                    const role = assignableEntities.roles?.find(r => r.id == assignment.assignable_id);
                    assignmentType = role ? role.name : 'admin';
                    break;
                case 'App\\Models\\Department':
                    assignmentType = 'admin'; // Default fallback
                    break;
                default:
                    assignmentType = 'admin';
            }
        }

        return {
            id: assignment.id,
            assignment_type: assignmentType,
            user_id: userId,
            is_required: assignment.is_required
        }
    }

    const getEntityName = (assignment) => {
        if (!assignment) {
            return 'Unknown Assignment'
        }

        // Handle new assignment structure
        if (assignment.assignment_type) {
            if (assignment.assignment_type === 'user') {
                const user = assignableEntities.users?.find(u => u.id == assignment.user_id)
                return user ? user.name : 'Unknown User'
            } else if (assignment.assignment_type === 'finance') {
                const user = assignableEntities.users?.find(u => u.id == assignment.user_id)
                return user ? `${user.name} (Finance)` : 'Unknown Finance User'
            } else {
                return assignment.assignment_type.charAt(0).toUpperCase() + assignment.assignment_type.slice(1)
            }
        }

        // Handle old assignment structure (fallback)
        if (assignment.assignable_type) {
            switch (assignment.assignable_type) {
                case 'App\\Models\\User':
                    const user = assignableEntities.users?.find(u => u.id == assignment.assignable_id)
                    return user ? user.name : 'Unknown User'
                case 'App\\Models\\FinanceAssignment':
                    // For old structure, get user_id from assignable relationship
                    const financeUserId = assignment.user_id || (assignment.assignable && assignment.assignable.user_id);
                    const financeUser = assignableEntities.users?.find(u => u.id == financeUserId)
                    return financeUser ? `${financeUser.name} (Finance)` : 'Unknown Finance User'
                case 'App\\Models\\Role':
                    const role = assignableEntities.roles?.find(r => r.id == assignment.assignable_id)
                    return role ? role.name : 'Unknown Role'
                case 'App\\Models\\Department':
                    const department = assignableEntities.departments?.find(d => d.id == assignment.assignable_id)
                    return department ? department.name : 'Unknown Department'
                default:
                    return 'Unknown Assignment'
            }
        }

        return 'Unknown Assignment'
    }

    if (loading) {
        return (
            <AppLayout auth={auth}>
                <div className="flex justify-center items-center h-64">
                    <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600"></div>
                </div>
            </AppLayout>
        )
    }

    return (
        <AppLayout auth={auth}>
            <Head title="Workflow Settings" />
            <div className="py-12">
                <div className="max-w-7xl mx-auto sm:px-6 lg:px-8">
                    <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div className="p-6 text-gray-900">
                            <div className="flex justify-between items-center mb-6">
                                <div>
                                    <h2 className="text-2xl font-bold text-gray-900">
                                        Workflow Steps Management
                                    </h2>
                                    <p className="text-sm text-gray-600 mt-1">
                                        Drag and drop steps to reorder them. The order determines the sequence of workflow execution.
                                    </p>
                                </div>
                                <button
                                    onClick={handleAddStep}
                                    className="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg flex items-center gap-2"
                                >
                                    <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4v16m8-8H4" />
                                    </svg>
                                    Add New Step
                                </button>
                            </div>

                            <DndContext
                                sensors={sensors}
                                collisionDetection={closestCenter}
                                onDragEnd={handleDragEnd}
                            >
                                <SortableContext
                                    items={steps.map(step => step.id)}
                                    strategy={verticalListSortingStrategy}
                                >
                                    <div className="space-y-4">
                                        {isReordering && (
                                            <div className="flex items-center justify-center py-2">
                                                <div className="flex items-center gap-2 text-blue-600">
                                                    <div className="animate-spin rounded-full h-4 w-4 border-b-2 border-blue-600"></div>
                                                    <span className="text-sm">Reordering steps...</span>
                                                </div>
                                            </div>
                                        )}
                                        {steps.map((step) => (
                                            <SortableStep
                                                key={step.id}
                                                step={step}
                                                onEdit={handleEditStep}
                                                onDelete={handleDeleteStep}
                                                getEntityName={getEntityName}
                                            />
                                        ))}

                                        {steps.length === 0 && (
                                            <div className="text-center py-12">
                                                <svg className="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                                                </svg>
                                                <h3 className="mt-2 text-sm font-medium text-gray-900">No workflow steps defined</h3>
                                                <p className="mt-1 text-sm text-gray-500">Start by adding a new step.</p>
                                            </div>
                                        )}
                                    </div>
                                </SortableContext>
                            </DndContext>
                        </div>
                    </div>
                </div>
            </div>

            {/* Modal for Add/Edit Step */}
            {showModal && (
                <div className="fixed inset-0 modal-backdrop overflow-y-auto h-full w-full z-50">
                    <div className="relative top-20 mx-auto p-5 border w-11/12 md:w-3/4 lg:w-1/2 shadow-lg rounded-md bg-white">
                        <div className="mt-3">
                            <h3 className="text-lg font-medium text-gray-900 mb-4">
                                {editingStep ? 'Edit Step' : 'Add New Step'}
                            </h3>

                            <div className="space-y-4">
                                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label className="block text-sm font-medium text-gray-700 mb-1">
                                            Step Name *
                                        </label>
                                        <div className="flex gap-3">
                                            <input
                                                type="text"
                                                value={stepForm.name}
                                                onChange={(e) => setStepForm(prev => ({ ...prev, name: e.target.value }))}
                                                className="flex-1 px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                                placeholder="e.g., Manager Approval"
                                            />
                                        </div>
                                    </div>

                                    <div>
                                        <label className="block text-sm font-medium text-gray-700 mb-1">
                                            Step Type *
                                        </label>
                                        <select
                                            value={stepForm.step_type}
                                            onChange={(e) => setStepForm(prev => ({ ...prev, step_type: e.target.value }))}
                                            className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                        >
                                            <option value="approval">Approval</option>
                                            <option value="verification">Verification</option>
                                            <option value="notification">Notification</option>
                                        </select>
                                    </div>
                                </div>

                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-1">
                                        Description
                                    </label>
                                    <textarea
                                        value={stepForm.description}
                                        onChange={(e) => setStepForm(prev => ({ ...prev, description: e.target.value }))}
                                        className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                        rows="3"
                                        placeholder="Step description..."
                                    />
                                </div>
                                <div className="checkbox-container">
                                                <div className="custom-checkbox">
                                                    <input
                                                        type="checkbox"
                                                        id="is_active"
                                                        checked={stepForm.is_active}
                                                        onChange={(e) => setStepForm(prev => ({ ...prev, is_active: e.target.checked }))}
                                                    />
                                                    <span className="checkmark"></span>
                                                </div>
                                                <label
                                                    htmlFor="is_active"
                                                    className="checkbox-label whitespace-nowrap"
                                                >
                                                    Active
                                                </label>
                                            </div>
                                <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                                    <div>
                                        <label className="block text-sm font-medium text-gray-700 mb-1">
                                            Order
                                        </label>
                                        <input
                                            type="number"
                                            value={stepForm.order_index}
                                            onChange={(e) => setStepForm(prev => ({ ...prev, order_index: parseInt(e.target.value) }))}
                                            className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                            min="0"
                                        />
                                    </div>

                                    <div>
                                        <label className="block text-sm font-medium text-gray-700 mb-1">
                                            Timeout
                                        </label>
                                        <div className="checkbox-container timeout-checkbox">
                                            <div className="custom-checkbox">
                                                <input
                                                    type="checkbox"
                                                    id="timeout_enabled"
                                                    checked={stepForm.timeout_hours !== null}
                                                    onChange={(e) => setStepForm(prev => ({
                                                        ...prev,
                                                        timeout_hours: e.target.checked ? 48 : null
                                                    }))}
                                                />
                                                <span className="checkmark"></span>
                                            </div>
                                            <label
                                                htmlFor="timeout_enabled"
                                                className="checkbox-label"
                                            >
                                                Set timeout
                                            </label>
                                        </div>
                                        {stepForm.timeout_hours !== null && (
                                            <input
                                                type="number"
                                                value={stepForm.timeout_hours}
                                                onChange={(e) => setStepForm(prev => ({ ...prev, timeout_hours: parseInt(e.target.value) || 48 }))}
                                                className="w-full mt-2 px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                                min="1"
                                                placeholder="Hours"
                                            />
                                        )}
                                        {stepForm.timeout_hours === null && (
                                            <div className="mt-2 text-sm text-gray-500 italic">
                                                Unlimited time
                                            </div>
                                        )}
                                    </div>

                                </div>

                                {/* Conditions Section */}
                                <div>
                                    <div className="flex justify-between items-center mb-2">
                                        <div>
                                            <label className="block text-sm font-medium text-gray-700">
                                                Execution Conditions
                                            </label>
                                            <p className="text-xs text-gray-500 mt-1">
                                                Define conditions that must be met for this step to execute
                                            </p>
                                        </div>
                                        <button
                                            type="button"
                                            onClick={addCondition}
                                            className="text-blue-600 hover:text-blue-800 text-sm"
                                        >
                                            + Add Condition
                                        </button>
                                    </div>

                                    {stepForm.conditions.length === 0 ? (
                                        <div className="text-center py-4 text-gray-500 text-sm">
                                            <svg className="mx-auto h-8 w-8 text-gray-400 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                            </svg>
                                            <p>No conditions defined. This step will always execute.</p>
                                            <p className="text-xs mt-1">Click "Add Condition" to set specific requirements.</p>
                                        </div>
                                    ) : (
                                        stepForm.conditions.map((condition, index) => (
                                            <div key={index} className="flex gap-2 mb-2 items-center">
                                                <select
                                                    value={condition.field}
                                                    onChange={(e) => updateCondition(index, 'field', e.target.value)}
                                                    className="px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                                >
                                                    <option value="amount">Amount</option>
                                                    <option value="status">Status</option>
                                                </select>

                                                <select
                                                    value={condition.operator}
                                                    onChange={(e) => updateCondition(index, 'operator', e.target.value)}
                                                    className="px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                                >
                                                    {condition.field === 'amount' ? (
                                                        <>
                                                            <option value=">">Greater than</option>
                                                            <option value=">=">Greater than or equal</option>
                                                            <option value="<">Less than</option>
                                                            <option value="<=">Less than or equal</option>
                                                            <option value="=">Equal</option>
                                                            <option value="!=">Not equal</option>
                                                        </>
                                                    ) : condition.field === 'status' ? (
                                                        <>
                                                            <option value="=">Equal</option>
                                                            <option value="!=">Not equal</option>
                                                        </>
                                                    ) : (
                                                        <>
                                                            <option value="=">Equal</option>
                                                            <option value="!=">Not equal</option>
                                                        </>
                                                    )}
                                                </select>

                                                {condition.field === 'amount' ? (
                                                    <input
                                                        type="number"
                                                        value={condition.value}
                                                        onChange={(e) => updateCondition(index, 'value', parseFloat(e.target.value) || 0)}
                                                        className="px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                                        placeholder="Amount value"
                                                        min="0"
                                                        step="0.01"
                                                    />
                                                ) : condition.field === 'status' ? (
                                                    <select
                                                        value={condition.value}
                                                        onChange={(e) => updateCondition(index, 'value', e.target.value)}
                                                        className="px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                                    >
                                                        <option value="">Select Status</option>
                                                        <option value="pending">Pending</option>
                                                        <option value="approved">Approved</option>
                                                        <option value="rejected">Rejected</option>
                                                        <option value="cancelled">Cancelled</option>
                                                    </select>
                                                ) : (
                                                    <input
                                                        type="text"
                                                        value={condition.value}
                                                        onChange={(e) => updateCondition(index, 'value', e.target.value)}
                                                        className="px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                                        placeholder="Value"
                                                    />
                                                )}

                                                <button
                                                    type="button"
                                                    onClick={() => removeCondition(index)}
                                                    className="text-red-600 hover:text-red-800 p-1 flex-shrink-0"
                                                    title="Remove condition"
                                                >
                                                    <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                                                    </svg>
                                                </button>
                                            </div>
                                        ))
                                    )}
                                </div>

                                {/* Assignments Section */}
                                <div>
                                    <div className="flex justify-between items-center mb-2">
                                        <div className="flex items-center gap-4">
                                            <label className="block text-sm font-medium text-gray-700">
                                                Assignments {!stepForm.auto_approve && <span className="text-red-500">*</span>}
                                            </label>
                                            <div className="checkbox-container">
                                                <div className="custom-checkbox">
                                                    <input
                                                        type="checkbox"
                                                        id="auto_approve"
                                                        checked={stepForm.auto_approve}
                                                        onChange={(e) => setStepForm(prev => ({
                                                            ...prev,
                                                            auto_approve: e.target.checked,
                                                            assignments: e.target.checked ? [] : prev.assignments
                                                        }))}
                                                    />
                                                    <span className="checkmark"></span>
                                                </div>
                                                <label
                                                    htmlFor="auto_approve"
                                                    className="checkbox-label"
                                                >
                                                    Auto Approve
                                                </label>
                                            </div>
                                        </div>
                                        <button
                                            type="button"
                                            onClick={addAssignment}
                                            className="text-blue-600 hover:text-blue-800 text-sm"
                                            disabled={stepForm.auto_approve}
                                        >
                                            + Add Assignment
                                        </button>
                                    </div>

                                    {stepForm.auto_approve ? (
                                        <div className="text-center py-4 text-gray-500 text-sm border-2 border-dashed border-gray-300 rounded-lg bg-gray-50">
                                            <svg className="mx-auto h-8 w-8 text-gray-400 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                            </svg>
                                            <p className="font-medium">Auto Approve Enabled</p>
                                            <p className="text-xs mt-1">This step will be automatically approved without requiring manual intervention.</p>
                                        </div>
                                    ) : (
                                        <>
                                            {(!stepForm.assignments || stepForm.assignments.length === 0) && (
                                                <div className="text-center py-4 text-gray-500 text-sm border-2 border-dashed border-gray-300 rounded-lg">
                                                    <svg className="mx-auto h-8 w-8 text-gray-400 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4v16m8-8H4" />
                                                    </svg>
                                                    <p>No assignments defined</p>
                                                    <p className="text-xs mt-1">Click "Add Assignment" to assign users/roles to this step.</p>
                                                </div>
                                            )}

                                            {stepForm.assignments.map((assignment, index) => (
                                        <div key={index} className="flex gap-2 mb-2">
                                            <select
                                                value={assignment.assignment_type}
                                                onChange={(e) => updateAssignment(index, 'assignment_type', e.target.value)}
                                                className={`px-3 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 ${
                                                    !assignment.assignment_type ? 'border-red-300 bg-red-50' : 'border-gray-300'
                                                }`}
                                                required
                                            >
                                                <option value="">Select Assignment Type *</option>
                                                <option value="admin">Admin</option>
                                                <option value="manager">Manager</option>
                                                <option value="finance">Finance</option>
                                                <option value="procurement">Procurement</option>
                                                <option value="user">User</option>
                                            </select>

                                            {assignment.assignment_type === 'user' && (
                                                <select
                                                    value={assignment.user_id}
                                                    onChange={(e) => updateAssignment(index, 'user_id', e.target.value)}
                                                    className={`px-3 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 ${
                                                        !assignment.user_id ? 'border-red-300 bg-red-50' : 'border-gray-300'
                                                    }`}
                                                    required
                                                >
                                                    <option value="">Select User *</option>
                                                    {assignableEntities.users.map(user => (
                                                        <option key={user.id} value={user.id}>
                                                            {user.name} ({user.email})
                                                        </option>
                                                    ))}
                                                </select>
                                            )}

                                            {assignment.assignment_type === 'finance' && (
                                                <select
                                                    value={assignment.user_id}
                                                    onChange={(e) => updateAssignment(index, 'user_id', e.target.value)}
                                                    className={`px-3 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 ${
                                                        !assignment.user_id ? 'border-red-300 bg-red-50' : 'border-gray-300'
                                                    }`}
                                                    required
                                                >
                                                    <option value="">Select Finance User *</option>
                                                    {assignableEntities.users
                                                        .filter(user => user.department_name === 'Finance')
                                                        .map(user => (
                                                            <option key={user.id} value={user.id}>
                                                                {user.name} ({user.email})
                                                            </option>
                                                        ))}
                                                </select>
                                            )}


                                            <div className="checkbox-container assignment-checkbox">
                                                <div className="custom-checkbox">
                                                    <input
                                                        type="checkbox"
                                                        id={`assignment_required_${index}`}
                                                        checked={assignment.is_required}
                                                        onChange={(e) => updateAssignment(index, 'is_required', e.target.checked)}
                                                    />
                                                    <span className="checkmark"></span>
                                                </div>
                                                <label
                                                    htmlFor={`assignment_required_${index}`}
                                                    className="checkbox-label"
                                                >
                                                    Required
                                                </label>
                                            </div>

                                            <button
                                                type="button"
                                                onClick={() => removeAssignment(index)}
                                                className="text-red-600 hover:text-red-800 p-1"
                                            >
                                                <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                                                </svg>
                                            </button>
                                        </div>
                                    ))}
                                        </>
                                    )}
                                </div>
                            </div>

                            <div className="flex justify-end gap-3 mt-6">
                                <button
                                    onClick={() => setShowModal(false)}
                                    className="px-4 py-2 text-gray-700 bg-gray-200 rounded-md hover:bg-gray-300"
                                >
                                    Cancel
                                </button>
                                <button
                                    onClick={handleSaveStep}
                                    className="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700"
                                >
                                    {editingStep ? 'Update' : 'Create'}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            )}

            <AlertModal
                show={showAlert}
                onClose={() => setShowAlert(false)}
                message={alertMessage}
                type={alertType}
            />

            <ConfirmationModal
                isOpen={showConfirm}
                onClose={() => setShowConfirm(false)}
                onConfirm={confirmAction}
                title="Confirm Delete"
                message="Are you sure you want to delete this step?"
                type="danger"
                confirmText="Delete"
                cancelText="Cancel"
            />
        </AppLayout>
    )
}
