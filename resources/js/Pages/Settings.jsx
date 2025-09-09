import { Head, Link } from '@inertiajs/react'
import AppLayout from '../Layouts/AppLayout'
import { useState, useEffect } from 'react'
import axios from 'axios'

export default function Settings({ auth }) {
    const [activeTab, setActiveTab] = useState('general')
    const [departments, setDepartments] = useState([])
    const [loading, setLoading] = useState(true)
    const [showDepartmentModal, setShowDepartmentModal] = useState(false)
    const [editingDepartment, setEditingDepartment] = useState(null)
    const [departmentForm, setDepartmentForm] = useState({
        name: '',
        description: '',
        role_id: ''
    })
    const [departmentErrors, setDepartmentErrors] = useState({})
    const [submitting, setSubmitting] = useState(false)
    const [settings, setSettings] = useState({
        emailNotifications: true,
        approvalThreshold: 1000,
        managerOnlyThreshold: 2000,
        ceoApprovalThreshold: 5000,
        managerApprovalRequired: true
    })
    const [settingsLoading, setSettingsLoading] = useState(true)
    const [settingsSaving, setSettingsSaving] = useState(false)

    useEffect(() => {
        fetchDepartments()
        fetchSettings()
    }, [])

    const fetchSettings = async () => {
        try {
            setSettingsLoading(true)
            const response = await axios.get('/api/admin/settings')
            if (response.data.success) {
                const settingsData = response.data.data
                setSettings({
                    emailNotifications: settingsData.email_notifications_enabled || true,
                    approvalThreshold: settingsData.auto_approval_threshold || 1000,
                    managerOnlyThreshold: settingsData.manager_only_threshold || 2000,
                    ceoApprovalThreshold: settingsData.ceo_approval_threshold || 5000,
                    managerApprovalRequired: settingsData.manager_approval_required !== false
                })
            }
        } catch (error) {
            console.error('Error fetching settings:', error)
        } finally {
            setSettingsLoading(false)
        }
    }

    const fetchDepartments = async () => {
        try {
            setLoading(true)
            const response = await axios.get('/api/admin/departments')
            if (response.data.success) {
                setDepartments(response.data.data)
            }
        } catch (error) {
            console.error('Error fetching departments:', error)
        } finally {
            setLoading(false)
        }
    }


    const handleDepartmentSubmit = async (e) => {
        e.preventDefault()
        setSubmitting(true)
        setDepartmentErrors({})

        try {
            const url = editingDepartment ? `/api/admin/departments/${editingDepartment.id}` : '/api/admin/departments'
            const method = editingDepartment ? 'put' : 'post'

            const response = await axios[method](url, departmentForm)

            if (response.data.success) {
                setShowDepartmentModal(false)
                setEditingDepartment(null)
                setDepartmentForm({ name: '', description: '', role_id: '' })
                fetchDepartments()
            }
        } catch (error) {
            if (error.response?.data?.errors) {
                setDepartmentErrors(error.response.data.errors)
            }
        } finally {
            setSubmitting(false)
        }
    }

    const handleEditDepartment = (department) => {
        setEditingDepartment(department)
        setDepartmentForm({
            name: department.name,
            description: department.description || '',
            role_id: department.role_id || ''
        })
        setShowDepartmentModal(true)
    }

    const handleDeleteDepartment = async (departmentId) => {
        if (window.confirm('Are you sure you want to delete this department?')) {
            try {
                await axios.delete(`/api/admin/departments/${departmentId}`)
                fetchDepartments()
            } catch (error) {
                console.error('Error deleting department:', error)
                alert('Error deleting department. Please try again.')
            }
        }
    }


    const handleSettingsChange = (e) => {
        const { name, value, type, checked } = e.target
        setSettings(prev => ({
            ...prev,
            [name]: type === 'checkbox' ? checked : value
        }))
    }


    const handleSettingsSubmit = async (e) => {
        e.preventDefault()

        try {
            setSettingsSaving(true)

            const settingsToSave = [
                { key: 'email_notifications_enabled', value: settings.emailNotifications, type: 'boolean' },
                { key: 'auto_approval_threshold', value: settings.approvalThreshold, type: 'number' },
                { key: 'manager_only_threshold', value: settings.managerOnlyThreshold, type: 'number' },
                { key: 'ceo_approval_threshold', value: settings.ceoApprovalThreshold, type: 'number' },
                { key: 'manager_approval_required', value: settings.managerApprovalRequired, type: 'boolean' }
            ]

            const response = await axios.put('/api/admin/settings', {
                settings: settingsToSave
            })

            if (response.data.success) {
                alert('Settings saved successfully!')
            } else {
                throw new Error(response.data.message || 'Failed to save settings')
            }
        } catch (error) {
            console.error('Error saving settings:', error)
            alert('Error saving settings: ' + (error.response?.data?.message || error.message))
        } finally {
            setSettingsSaving(false)
        }
    }

    const tabs = [
        { id: 'general', name: 'General Settings', icon: '⚙️' },
        { id: 'departments', name: 'Department Management', icon: '🏢' }
    ]

    if (loading || settingsLoading) {
        return (
            <AppLayout title="Settings" auth={auth}>
                <div className="flex items-center justify-center h-64">
                    <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600"></div>
                </div>
            </AppLayout>
        )
    }

    return (
        <AppLayout title="Settings" auth={auth}>
            <div className="space-y-6">
                {/* Header */}
                <div>
                    <h1 className="text-xl lg:text-2xl font-bold text-gray-900">Settings</h1>
                    <p className="text-sm lg:text-base text-gray-600 mt-1">Manage your application settings and preferences.</p>
                </div>

                {/* Tabs */}
                <div className="border-b border-gray-200">
                    <nav className="-mb-px flex flex-col sm:flex-row space-y-2 sm:space-y-0 sm:space-x-8">
                        {tabs.map((tab) => (
                            <button
                                key={tab.id}
                                onClick={() => setActiveTab(tab.id)}
                                className={`py-2 px-1 border-b-2 font-medium text-sm ${
                                    activeTab === tab.id
                                        ? 'border-blue-500 text-blue-600'
                                        : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
                                }`}
                            >
                                <span className="mr-2">{tab.icon}</span>
                                <span className="hidden sm:inline">{tab.name}</span>
                                <span className="sm:hidden">{tab.name.split(' ')[0]}</span>
                            </button>
                        ))}
                    </nav>
                </div>

                {/* Tab Content */}
                <div className="bg-white shadow-sm rounded-lg overflow-hidden">
                    {activeTab === 'general' && (
                        <div className="p-4 lg:p-6">
                            <h3 className="text-base lg:text-lg font-medium text-gray-900 mb-4">General Settings</h3>
                            <form onSubmit={handleSettingsSubmit} className="space-y-6 lg:space-y-8">
                                {/* Notifications Section */}
                                <div>
                                    <h4 className="text-md font-medium text-gray-900 mb-4">Notifications</h4>
                                    <div className="space-y-4">
                                        <div className="flex items-center">
                                            <input
                                                id="emailNotifications"
                                                name="emailNotifications"
                                                type="checkbox"
                                                checked={settings.emailNotifications}
                                                onChange={handleSettingsChange}
                                                className="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
                                            />
                                            <label htmlFor="emailNotifications" className="ml-2 block text-sm text-gray-900">
                                                Email notifications
                                            </label>
                                        </div>
                                    </div>
                                </div>

                                {/* Approval Workflow Section */}
                                <div>
                                    <h4 className="text-md font-medium text-gray-900 mb-4">Approval Workflow</h4>
                                    <div className="space-y-6">

                                        <div className="grid grid-cols-1 lg:grid-cols-3 gap-4 lg:gap-6">
                                            <div>
                                                <label htmlFor="approvalThreshold" className="block text-sm font-medium text-gray-700">
                                                    Auto-approval Threshold (AFN)
                                                </label>
                                                <input
                                                    type="number"
                                                    id="approvalThreshold"
                                                    name="approvalThreshold"
                                                    value={settings.approvalThreshold}
                                                    onChange={handleSettingsChange}
                                                    min="0"
                                                    step="100"
                                                    className="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
                                                    placeholder="1000"
                                                />
                                                <p className="mt-1 text-xs text-gray-500">
                                                    Auto-approve requests below this amount
                                                </p>
                                            </div>

                                            <div>
                                                <label htmlFor="managerOnlyThreshold" className="block text-sm font-medium text-gray-700">
                                                    Manager-Only Threshold (AFN) *
                                                </label>
                                                <input
                                                    type="number"
                                                    id="managerOnlyThreshold"
                                                    name="managerOnlyThreshold"
                                                    value={settings.managerOnlyThreshold}
                                                    onChange={handleSettingsChange}
                                                    min="0"
                                                    step="100"
                                                    className="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
                                                    placeholder="2000"
                                                />
                                                <p className="mt-1 text-xs text-gray-500">
                                                    Manager approval only (no admin required)
                                                </p>
                                            </div>

                                            <div>
                                                <label htmlFor="ceoApprovalThreshold" className="block text-sm font-medium text-gray-700">
                                                    CEO Approval Threshold (AFN) *
                                                </label>
                                                <input
                                                    type="number"
                                                    id="ceoApprovalThreshold"
                                                    name="ceoApprovalThreshold"
                                                    value={settings.ceoApprovalThreshold}
                                                    onChange={handleSettingsChange}
                                                    min="0"
                                                    step="100"
                                                    className="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
                                                    placeholder="5000"
                                                />
                                                <p className="mt-1 text-xs text-gray-500">
                                                    Requires both manager and CEO approval
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div className="flex justify-end">
                                    <button
                                        type="submit"
                                        disabled={settingsSaving}
                                        className="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-md font-medium disabled:opacity-50 disabled:cursor-not-allowed flex items-center"
                                    >
                                        {settingsSaving ? (
                                            <>
                                                <svg className="animate-spin -ml-1 mr-3 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                                    <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"></circle>
                                                    <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                                </svg>
                                                Saving...
                                            </>
                                        ) : (
                                            'Save Settings'
                                        )}
                                    </button>
                                </div>
                            </form>
                        </div>
                    )}

                    {activeTab === 'departments' && (
                        <div className="p-4 lg:p-6">
                            <div className="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-4 mb-6">
                                <h3 className="text-base lg:text-lg font-medium text-gray-900">Department Management</h3>
                                <button
                                    onClick={() => {
                                        setEditingDepartment(null)
                                        setDepartmentForm({ name: '', description: '' })
                                        setShowDepartmentModal(true)
                                    }}
                                    className="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md font-medium text-center"
                                >
                                    Add Department
                                </button>
                            </div>

                            {/* Desktop Table */}
                            <div className="hidden lg:block overflow-hidden shadow ring-1 ring-black ring-opacity-5 md:rounded-lg">
                                <table className="min-w-full divide-y divide-gray-300">
                                    <thead className="bg-gray-50">
                                        <tr>
                                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                Name
                                            </th>
                                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                Description
                                            </th>
                                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                Role
                                            </th>
                                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                Users
                                            </th>
                                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                Actions
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody className="bg-white divide-y divide-gray-200">
                                        {departments.map((department) => (
                                            <tr key={department.id} className="hover:bg-gray-50">
                                                <td className="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                                    {department.name}
                                                </td>
                                                <td className="px-6 py-4 text-sm text-gray-500">
                                                    {department.description || 'No description'}
                                                </td>
                                                <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                    {department.role?.name || 'N/A'}
                                                </td>
                                                <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                    {department.users_count || 0} users
                                                </td>
                                                <td className="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                    <div className="flex space-x-2">
                                                        <button
                                                            onClick={() => handleEditDepartment(department)}
                                                            className="text-blue-600 hover:text-blue-900"
                                                        >
                                                            Edit
                                                        </button>
                                                        <button
                                                            onClick={() => handleDeleteDepartment(department.id)}
                                                            className="text-red-600 hover:text-red-900"
                                                        >
                                                            Delete
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>

                            {/* Mobile Cards */}
                            <div className="lg:hidden space-y-4">
                                {departments.map((department) => (
                                    <div key={department.id} className="bg-white border border-gray-200 rounded-lg p-4 shadow-sm">
                                        <div className="flex items-start justify-between mb-3">
                                            <h3 className="text-sm font-medium text-gray-900">{department.name}</h3>
                                            <div className="flex space-x-2">
                                                <button
                                                    onClick={() => handleEditDepartment(department)}
                                                    className="text-blue-600 hover:text-blue-900 text-xs"
                                                >
                                                    Edit
                                                </button>
                                                <button
                                                    onClick={() => handleDeleteDepartment(department.id)}
                                                    className="text-red-600 hover:text-red-900 text-xs"
                                                >
                                                    Delete
                                                </button>
                                            </div>
                                        </div>

                                        <div className="space-y-2 text-sm">
                                            <div>
                                                <span className="text-gray-500">Description:</span>
                                                <p className="text-gray-900">{department.description || 'No description'}</p>
                                            </div>
                                            <div className="flex justify-between">
                                                <span className="text-gray-500">Role:</span>
                                                <span className="text-gray-900">{department.role?.name || 'N/A'}</span>
                                            </div>
                                            <div className="flex justify-between">
                                                <span className="text-gray-500">Users:</span>
                                                <span className="text-gray-900">{department.users_count || 0} users</span>
                                            </div>
                                        </div>
                                    </div>
                                ))}
                            </div>

                            {departments.length === 0 && (
                                <div className="text-center py-12">
                                    <div className="text-gray-400 text-6xl mb-4">🏢</div>
                                    <h3 className="text-lg font-medium text-gray-900 mb-2">No departments found</h3>
                                    <p className="text-gray-500">Get started by adding your first department</p>
                                </div>
                            )}
                        </div>
                    )}


                </div>

                {/* Department Modal */}
                {showDepartmentModal && (
                    <div className="fixed inset-0 modal-backdrop overflow-y-auto h-full w-full z-50 flex items-center justify-center p-4">
                        <div className="relative w-full max-w-2xl bg-white rounded-lg shadow-xl">
                            <div className="p-6">
                                <h3 className="text-lg font-medium text-gray-900 mb-6">
                                    {editingDepartment ? 'Edit Department' : 'Add New Department'}
                                </h3>
                                <form onSubmit={handleDepartmentSubmit} className="space-y-6">
                                    <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                                        <div>
                                            <label className="block text-sm font-medium text-gray-700 mb-2">
                                                Department Name *
                                            </label>
                                            <input
                                                type="text"
                                                value={departmentForm.name}
                                                onChange={(e) => setDepartmentForm(prev => ({ ...prev, name: e.target.value }))}
                                                className={`block w-full px-3 py-2 border rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm ${departmentErrors.name ? 'border-red-300' : 'border-gray-300'}`}
                                                required
                                            />
                                            {departmentErrors.name && <p className="mt-1 text-sm text-red-600">{departmentErrors.name[0]}</p>}
                                        </div>
                                        <div>
                                            <label className="block text-sm font-medium text-gray-700 mb-2">
                                                Role *
                                            </label>
                                            <select
                                                value={departmentForm.role_id}
                                                onChange={(e) => setDepartmentForm(prev => ({ ...prev, role_id: e.target.value }))}
                                                className={`block w-full px-3 py-2 border rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm ${departmentErrors.role_id ? 'border-red-300' : 'border-gray-300'}`}
                                                required
                                            >
                                                <option value="">Select Role</option>
                                                <option value="1">admin</option>
                                                <option value="2">manager</option>
                                                <option value="3">employee</option>
                                                <option value="4">procurement</option>
                                            </select>
                                            {departmentErrors.role_id && <p className="mt-1 text-sm text-red-600">{departmentErrors.role_id[0]}</p>}
                                        </div>
                                    </div>
                                    <div>
                                        <label className="block text-sm font-medium text-gray-700 mb-2">
                                            Description
                                        </label>
                                        <textarea
                                            value={departmentForm.description}
                                            onChange={(e) => setDepartmentForm(prev => ({ ...prev, description: e.target.value }))}
                                            rows={4}
                                            className={`block w-full px-3 py-2 border rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm ${departmentErrors.description ? 'border-red-300' : 'border-gray-300'}`}
                                        />
                                        {departmentErrors.description && <p className="mt-1 text-sm text-red-600">{departmentErrors.description[0]}</p>}
                                    </div>
                                    <div className="flex justify-end space-x-3 pt-6 border-t border-gray-200">
                                        <button
                                            type="button"
                                            onClick={() => setShowDepartmentModal(false)}
                                            className="px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50"
                                        >
                                            Cancel
                                        </button>
                                        <button
                                            type="submit"
                                            disabled={submitting}
                                            className="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-md text-sm font-medium disabled:opacity-50"
                                        >
                                            {submitting ? 'Saving...' : (editingDepartment ? 'Update Department' : 'Create Department')}
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
