import { Head, Link } from '@inertiajs/react'
import AppLayout from '../Layouts/AppLayout'
import { useState, useEffect } from 'react'
import axios from 'axios'

export default function Settings({ auth }) {
    const [activeTab, setActiveTab] = useState('general')
    const [departments, setDepartments] = useState([])
    const [roles, setRoles] = useState([])
    const [loading, setLoading] = useState(true)
    const [showDepartmentModal, setShowDepartmentModal] = useState(false)
    const [showRoleModal, setShowRoleModal] = useState(false)
    const [editingDepartment, setEditingDepartment] = useState(null)
    const [editingRole, setEditingRole] = useState(null)
    const [departmentForm, setDepartmentForm] = useState({
        name: '',
        description: '',
        role_id: ''
    })
    const [roleForm, setRoleForm] = useState({
        name: '',
        description: '',
        permissions: [],
        is_active: true
    })
    const [departmentErrors, setDepartmentErrors] = useState({})
    const [roleErrors, setRoleErrors] = useState({})
    const [submitting, setSubmitting] = useState(false)
    const [settings, setSettings] = useState({
        emailNotifications: true,
        smsNotifications: false,
        autoApproval: false,
        approvalThreshold: 1000,
        workingHours: '9:00 AM - 5:00 PM',
        timezone: 'UTC+4'
    })

    const availablePermissions = [
        { value: 'submit_requests', label: 'Submit Requests' },
        { value: 'approve_requests', label: 'Approve Requests' },
        { value: 'view_all_requests', label: 'View All Requests' },
        { value: 'view_own_requests', label: 'View Own Requests' },
        { value: 'manage_team', label: 'Manage Team' },
        { value: 'manage_users', label: 'Manage Users' },
        { value: 'manage_departments', label: 'Manage Departments' },
        { value: 'manage_roles', label: 'Manage Roles' },
        { value: 'view_reports', label: 'View Reports' },
        { value: 'view_audit_logs', label: 'View Audit Logs' },
        { value: 'manage_approval_rules', label: 'Manage Approval Rules' },
        { value: 'manage_procurement', label: 'Manage Procurement' },
        { value: '*', label: 'All Permissions (Admin)' }
    ]

    useEffect(() => {
        fetchDepartments()
        fetchRoles()
    }, [])

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

    const fetchRoles = async () => {
        try {
            const response = await axios.get('/api/admin/roles')
            if (response.data.success) {
                setRoles(response.data.data)
            }
        } catch (error) {
            console.error('Error fetching roles:', error)
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

    // Role management functions
    const handleRoleSubmit = async (e) => {
        e.preventDefault()
        setSubmitting(true)
        setRoleErrors({})

        try {
            const url = editingRole
                ? `/api/admin/roles/${editingRole.id}`
                : '/api/admin/roles'
            const method = editingRole ? 'put' : 'post'

            const response = await axios[method](url, roleForm)

            if (response.data.success) {
                setShowRoleModal(false)
                setEditingRole(null)
                setRoleForm({
                    name: '',
                    description: '',
                    permissions: [],
                    is_active: true
                })
                fetchRoles()
            }
        } catch (error) {
            if (error.response?.data?.errors) {
                setRoleErrors(error.response.data.errors)
            }
        } finally {
            setSubmitting(false)
        }
    }

    const handleEditRole = (role) => {
        setEditingRole(role)
        setRoleForm({
            name: role.name,
            description: role.description || '',
            permissions: role.permissions || [],
            is_active: role.is_active
        })
        setShowRoleModal(true)
    }

    const handleDeleteRole = async (roleId) => {
        if (window.confirm('Are you sure you want to delete this role?')) {
            try {
                await axios.delete(`/api/admin/roles/${roleId}`)
                fetchRoles()
            } catch (error) {
                console.error('Error deleting role:', error)
                alert('Error deleting role. Please try again.')
            }
        }
    }

    const handlePermissionToggle = (permission) => {
        setRoleForm(prev => ({
            ...prev,
            permissions: prev.permissions.includes(permission)
                ? prev.permissions.filter(p => p !== permission)
                : [...prev.permissions, permission]
        }))
    }

    const handleSelectAllPermissions = () => {
        const allPermissions = availablePermissions.map(p => p.value)
        setRoleForm(prev => ({
            ...prev,
            permissions: prev.permissions.length === allPermissions.length ? [] : allPermissions
        }))
    }

    const handleSettingsChange = (e) => {
        const { name, value, type, checked } = e.target
        setSettings(prev => ({
            ...prev,
            [name]: type === 'checkbox' ? checked : value
        }))
    }

    const handleSettingsSubmit = (e) => {
        e.preventDefault()
        // In real app, this would save to API
        console.log('Saving settings:', settings)
        alert('Settings saved successfully!')
    }

    const tabs = [
        { id: 'general', name: 'General Settings', icon: '⚙️' },
        { id: 'departments', name: 'Department Management', icon: '🏢' },
        { id: 'roles', name: 'Role Management', icon: '👥' },
        { id: 'notifications', name: 'Notifications', icon: '🔔' },
        { id: 'security', name: 'Security', icon: '🔒' }
    ]

    if (loading) {
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
                    <h1 className="text-2xl font-bold text-gray-900">Settings</h1>
                    <p className="text-gray-600 mt-1">Manage your application settings and preferences.</p>
                </div>

                {/* Tabs */}
                <div className="border-b border-gray-200">
                    <nav className="-mb-px flex space-x-8">
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
                                {tab.name}
                            </button>
                        ))}
                    </nav>
                </div>

                {/* Tab Content */}
                <div className="bg-white shadow-sm rounded-lg overflow-hidden">
                    {activeTab === 'general' && (
                        <div className="p-6">
                            <h3 className="text-lg font-medium text-gray-900 mb-4">General Settings</h3>
                            <form onSubmit={handleSettingsSubmit} className="space-y-6">
                                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label htmlFor="workingHours" className="block text-sm font-medium text-gray-700">
                                            Working Hours
                                        </label>
                                        <input
                                            type="text"
                                            id="workingHours"
                                            name="workingHours"
                                            value={settings.workingHours}
                                            onChange={handleSettingsChange}
                                            className="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
                                        />
                                    </div>
                                    <div>
                                        <label htmlFor="timezone" className="block text-sm font-medium text-gray-700">
                                            Timezone
                                        </label>
                                        <select
                                            id="timezone"
                                            name="timezone"
                                            value={settings.timezone}
                                            onChange={handleSettingsChange}
                                            className="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
                                        >
                                            <option value="UTC+4">UTC+4 (Kabul)</option>
                                            <option value="UTC+5">UTC+5 (Karachi)</option>
                                            <option value="UTC+0">UTC+0 (London)</option>
                                            <option value="UTC-5">UTC-5 (New York)</option>
                                        </select>
                                    </div>
                                </div>
                                <div className="flex justify-end">
                                    <button
                                        type="submit"
                                        className="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-md font-medium"
                                    >
                                        Save Settings
                                    </button>
                                </div>
                            </form>
                        </div>
                    )}

                    {activeTab === 'departments' && (
                        <div className="p-6">
                            <div className="flex justify-between items-center mb-6">
                                <h3 className="text-lg font-medium text-gray-900">Department Management</h3>
                                <button
                                    onClick={() => {
                                        setEditingDepartment(null)
                                        setDepartmentForm({ name: '', description: '' })
                                        setShowDepartmentModal(true)
                                    }}
                                    className="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md font-medium"
                                >
                                    Add Department
                                </button>
                            </div>

                            <div className="overflow-hidden shadow ring-1 ring-black ring-opacity-5 md:rounded-lg">
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

                            {departments.length === 0 && (
                                <div className="text-center py-12">
                                    <div className="text-gray-400 text-6xl mb-4">🏢</div>
                                    <h3 className="text-lg font-medium text-gray-900 mb-2">No departments found</h3>
                                    <p className="text-gray-500">Get started by adding your first department</p>
                                </div>
                            )}
                        </div>
                    )}

                    {activeTab === 'roles' && (
                        <div className="p-6">
                            <div className="flex justify-between items-center mb-6">
                                <h3 className="text-lg font-medium text-gray-900">Role Management</h3>
                                <button
                                    onClick={() => {
                                        setEditingRole(null)
                                        setRoleForm({
                                            name: '',
                                            display_name: '',
                                            description: '',
                                            permissions: [],
                                            is_active: true
                                        })
                                        setShowRoleModal(true)
                                    }}
                                    className="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md font-medium"
                                >
                                    Add Role
                                </button>
                            </div>

                            <div className="overflow-hidden shadow ring-1 ring-black ring-opacity-5 md:rounded-lg">
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
                                                Permissions
                                            </th>
                                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                Status
                                            </th>
                                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                Actions
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody className="bg-white divide-y divide-gray-200">
                                        {roles.map((role) => (
                                            <tr key={role.id} className="hover:bg-gray-50">
                                                <td className="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                                    {role.name}
                                                </td>
                                                <td className="px-6 py-4 text-sm text-gray-500">
                                                    {role.description || 'No description'}
                                                </td>
                                                <td className="px-6 py-4 text-sm text-gray-500">
                                                    <div className="flex flex-wrap gap-1">
                                                        {role.permissions && role.permissions.length > 0 ? (
                                                            role.permissions.map((permission, index) => (
                                                                <span
                                                                    key={index}
                                                                    className="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800"
                                                                >
                                                                    {availablePermissions.find(p => p.value === permission)?.label || permission}
                                                                </span>
                                                            ))
                                                        ) : (
                                                            <span className="text-gray-400">No permissions</span>
                                                        )}
                                                    </div>
                                                </td>
                                                <td className="px-6 py-4 whitespace-nowrap">
                                                    <span className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${
                                                        role.is_active
                                                            ? 'bg-green-100 text-green-800'
                                                            : 'bg-red-100 text-red-800'
                                                    }`}>
                                                        {role.is_active ? 'Active' : 'Inactive'}
                                                    </span>
                                                </td>
                                                <td className="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                    <div className="flex space-x-2">
                                                        <button
                                                            onClick={() => handleEditRole(role)}
                                                            className="text-blue-600 hover:text-blue-900"
                                                        >
                                                            Edit
                                                        </button>
                                                        <button
                                                            onClick={() => handleDeleteRole(role.id)}
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

                            {roles.length === 0 && (
                                <div className="text-center py-12">
                                    <div className="text-gray-400 text-6xl mb-4">👥</div>
                                    <h3 className="text-lg font-medium text-gray-900 mb-2">No roles found</h3>
                                    <p className="text-gray-500">Get started by adding your first role</p>
                                </div>
                            )}
                        </div>
                    )}

                    {activeTab === 'notifications' && (
                        <div className="p-6">
                            <h3 className="text-lg font-medium text-gray-900 mb-4">Notification Settings</h3>
                            <form onSubmit={handleSettingsSubmit} className="space-y-6">
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
                                    <div className="flex items-center">
                                        <input
                                            id="smsNotifications"
                                            name="smsNotifications"
                                            type="checkbox"
                                            checked={settings.smsNotifications}
                                            onChange={handleSettingsChange}
                                            className="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
                                        />
                                        <label htmlFor="smsNotifications" className="ml-2 block text-sm text-gray-900">
                                            SMS notifications
                                        </label>
                                    </div>
                                </div>
                                <div className="flex justify-end">
                                    <button
                                        type="submit"
                                        className="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-md font-medium"
                                    >
                                        Save Settings
                                    </button>
                                </div>
                            </form>
                        </div>
                    )}

                    {activeTab === 'security' && (
                        <div className="p-6">
                            <h3 className="text-lg font-medium text-gray-900 mb-4">Security Settings</h3>
                            <form onSubmit={handleSettingsSubmit} className="space-y-6">
                                <div className="space-y-4">
                                    <div className="flex items-center">
                                        <input
                                            id="autoApproval"
                                            name="autoApproval"
                                            type="checkbox"
                                            checked={settings.autoApproval}
                                            onChange={handleSettingsChange}
                                            className="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
                                        />
                                        <label htmlFor="autoApproval" className="ml-2 block text-sm text-gray-900">
                                            Enable auto-approval for small amounts
                                        </label>
                                    </div>
                                    <div>
                                        <label htmlFor="approvalThreshold" className="block text-sm font-medium text-gray-700">
                                            Auto-approval threshold (AFN)
                                        </label>
                                        <input
                                            type="number"
                                            id="approvalThreshold"
                                            name="approvalThreshold"
                                            value={settings.approvalThreshold}
                                            onChange={handleSettingsChange}
                                            className="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
                                        />
                                    </div>
                                </div>
                                <div className="flex justify-end">
                                    <button
                                        type="submit"
                                        className="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-md font-medium"
                                    >
                                        Save Settings
                                    </button>
                                </div>
                            </form>
                        </div>
                    )}
                </div>

                {/* Department Modal */}
                {showDepartmentModal && (
                    <div className="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
                        <div className="relative top-20 mx-auto p-5 border w-11/12 md:w-1/2 lg:w-1/3 shadow-lg rounded-md bg-white">
                            <div className="mt-3">
                                <h3 className="text-lg font-medium text-gray-900 mb-4">
                                    {editingDepartment ? 'Edit Department' : 'Add New Department'}
                                </h3>
                                <form onSubmit={handleDepartmentSubmit} className="space-y-4">
                                    <div>
                                        <label className="block text-sm font-medium text-gray-700 mb-1">
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
                                        <label className="block text-sm font-medium text-gray-700 mb-1">
                                            Description
                                        </label>
                                        <textarea
                                            value={departmentForm.description}
                                            onChange={(e) => setDepartmentForm(prev => ({ ...prev, description: e.target.value }))}
                                            rows={3}
                                            className={`block w-full px-3 py-2 border rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm ${departmentErrors.description ? 'border-red-300' : 'border-gray-300'}`}
                                        />
                                        {departmentErrors.description && <p className="mt-1 text-sm text-red-600">{departmentErrors.description[0]}</p>}
                                    </div>
                                    <div>
                                        <label className="block text-sm font-medium text-gray-700 mb-1">
                                            Role *
                                        </label>
                                        <select
                                            value={departmentForm.role_id}
                                            onChange={(e) => setDepartmentForm(prev => ({ ...prev, role_id: e.target.value }))}
                                            className={`block w-full px-3 py-2 border rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm ${departmentErrors.role_id ? 'border-red-300' : 'border-gray-300'}`}
                                            required
                                        >
                                            <option value="">Select Role</option>
                                            {roles.map(role => (
                                                <option key={role.id} value={role.id}>{role.name}</option>
                                            ))}
                                        </select>
                                        {departmentErrors.role_id && <p className="mt-1 text-sm text-red-600">{departmentErrors.role_id[0]}</p>}
                                    </div>
                                    <div className="flex justify-end space-x-3 pt-4">
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
                {/* Role Modal */}
                {showRoleModal && (
                    <div className="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
                        <div className="relative top-10 mx-auto p-5 border w-11/12 md:w-2/3 lg:w-1/2 shadow-lg rounded-md bg-white">
                            <div className="mt-3">
                                <h3 className="text-lg font-medium text-gray-900 mb-4">
                                    {editingRole ? 'Edit Role' : 'Add New Role'}
                                </h3>
                                <form onSubmit={handleRoleSubmit} className="space-y-4">
                                    <div>
                                        <label className="block text-sm font-medium text-gray-700 mb-1">
                                            Role Name *
                                        </label>
                                        <input
                                            type="text"
                                            value={roleForm.name}
                                            onChange={(e) => setRoleForm(prev => ({ ...prev, name: e.target.value }))}
                                            className={`block w-full px-3 py-2 border rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm ${roleErrors.name ? 'border-red-300' : 'border-gray-300'}`}
                                            placeholder="e.g., admin, manager, employee"
                                            required
                                        />
                                        {roleErrors.name && <p className="mt-1 text-sm text-red-600">{roleErrors.name[0]}</p>}
                                    </div>

                                    <div>
                                        <label className="block text-sm font-medium text-gray-700 mb-1">
                                            Description
                                        </label>
                                        <textarea
                                            value={roleForm.description}
                                            onChange={(e) => setRoleForm(prev => ({ ...prev, description: e.target.value }))}
                                            rows={3}
                                            className={`block w-full px-3 py-2 border rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm ${roleErrors.description ? 'border-red-300' : 'border-gray-300'}`}
                                            placeholder="Describe the role's responsibilities..."
                                        />
                                        {roleErrors.description && <p className="mt-1 text-sm text-red-600">{roleErrors.description[0]}</p>}
                                    </div>

                                    <div>
                                        <div className="flex justify-between items-center mb-2">
                                            <label className="block text-sm font-medium text-gray-700">
                                                Permissions
                                            </label>
                                            <button
                                                type="button"
                                                onClick={handleSelectAllPermissions}
                                                className="text-sm text-blue-600 hover:text-blue-800 font-medium"
                                            >
                                                {roleForm.permissions.length === availablePermissions.length ? 'Deselect All' : 'Select All'}
                                            </button>
                                        </div>
                                        <div className="grid grid-cols-2 md:grid-cols-3 gap-2 max-h-48 overflow-y-auto border border-gray-300 rounded-md p-3">
                                            {availablePermissions.map((permission) => (
                                                <label key={permission.value} className="flex items-center space-x-2">
                                                    <input
                                                        type="checkbox"
                                                        checked={roleForm.permissions.includes(permission.value)}
                                                        onChange={() => handlePermissionToggle(permission.value)}
                                                        className="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
                                                    />
                                                    <span className="text-sm text-gray-700">{permission.label}</span>
                                                </label>
                                            ))}
                                        </div>
                                        {roleErrors.permissions && <p className="mt-1 text-sm text-red-600">{roleErrors.permissions[0]}</p>}
                                    </div>

                                    <div className="flex items-center">
                                        <input
                                            type="checkbox"
                                            id="is_active"
                                            checked={roleForm.is_active}
                                            onChange={(e) => setRoleForm(prev => ({ ...prev, is_active: e.target.checked }))}
                                            className="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
                                        />
                                        <label htmlFor="is_active" className="ml-2 block text-sm text-gray-700">
                                            Active Role
                                        </label>
                                    </div>

                                    <div className="flex justify-end space-x-3 pt-4">
                                        <button
                                            type="button"
                                            onClick={() => setShowRoleModal(false)}
                                            className="px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50"
                                        >
                                            Cancel
                                        </button>
                                        <button
                                            type="submit"
                                            disabled={submitting}
                                            className="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-md text-sm font-medium disabled:opacity-50"
                                        >
                                            {submitting ? 'Saving...' : (editingRole ? 'Update Role' : 'Create Role')}
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
