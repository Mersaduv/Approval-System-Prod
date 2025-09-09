import { Head, Link } from '@inertiajs/react'
import AppLayout from '../Layouts/AppLayout'
import { useState, useEffect } from 'react'
import axios from 'axios'

export default function Users({ auth }) {
    const [users, setUsers] = useState([])
    const [departments, setDepartments] = useState([])
    const [loading, setLoading] = useState(true)
    const [searchTerm, setSearchTerm] = useState('')
    const [roleFilter, setRoleFilter] = useState('')
    const [departmentFilter, setDepartmentFilter] = useState('')
    const [showModal, setShowModal] = useState(false)
    const [editingUser, setEditingUser] = useState(null)
    const [formData, setFormData] = useState({
        full_name: '',
        email: '',
        password: '',
        department_id: '',
        role_id: ''
    })
    const [errors, setErrors] = useState({})
    const [submitting, setSubmitting] = useState(false)

    // Fixed roles - no need to fetch from API
    const fixedRoles = [
        { id: 1, name: 'admin', description: 'Full system access' },
        { id: 2, name: 'manager', description: 'Department manager' },
        { id: 3, name: 'employee', description: 'Basic employee' },
        { id: 4, name: 'procurement', description: 'Procurement team member' }
    ]

    useEffect(() => {
        fetchUsers()
        fetchDepartments()
    }, [])

    const fetchUsers = async () => {
        try {
            setLoading(true)
            const response = await axios.get('/api/admin/users')
            if (response.data.success) {
                setUsers(response.data.data.data)
            }
        } catch (error) {
            console.error('Error fetching users:', error)
        } finally {
            setLoading(false)
        }
    }

    const fetchDepartments = async () => {
        try {
            const response = await axios.get('/api/admin/departments')
            if (response.data.success) {
                setDepartments(response.data.data)
            }
        } catch (error) {
            console.error('Error fetching departments:', error)
        }
    }


    const handleSubmit = async (e) => {
        e.preventDefault()
        setSubmitting(true)
        setErrors({})

        try {
            const url = editingUser ? `/api/admin/users/${editingUser.id}` : '/api/admin/users'
            const method = editingUser ? 'put' : 'post'

            const response = await axios[method](url, formData)

            if (response.data.success) {
                setShowModal(false)
                setEditingUser(null)
                setFormData({
                    full_name: '',
                    email: '',
                    password: '',
                    department_id: '',
                    role_id: ''
                })
                fetchUsers()
            }
        } catch (error) {
            if (error.response?.data?.errors) {
                setErrors(error.response.data.errors)
            }
        } finally {
            setSubmitting(false)
        }
    }

    const handleEdit = (user) => {
        setEditingUser(user)
        setFormData({
            full_name: user.full_name,
            email: user.email,
            password: '',
            department_id: user.department_id,
            role_id: user.role_id
        })
        setShowModal(true)
    }

    const handleDelete = async (userId) => {
        if (window.confirm('Are you sure you want to delete this user?')) {
            try {
                await axios.delete(`/api/admin/users/${userId}`)
                fetchUsers()
            } catch (error) {
                console.error('Error deleting user:', error)
                alert('Error deleting user. Please try again.')
            }
        }
    }


    const filteredUsers = users.filter(user => {
        const matchesSearch = user.full_name.toLowerCase().includes(searchTerm.toLowerCase()) ||
                            user.email.toLowerCase().includes(searchTerm.toLowerCase()) ||
                            (user.role?.name || '').toLowerCase().includes(searchTerm.toLowerCase())
        const matchesRole = !roleFilter || user.role_id == roleFilter
        const matchesDepartment = !departmentFilter || user.department_id == departmentFilter
        return matchesSearch && matchesRole && matchesDepartment
    })

    const getStatusColor = (status) => {
        switch (status?.toLowerCase()) {
            case 'active': return 'bg-green-100 text-green-800'
            case 'inactive': return 'bg-red-100 text-red-800'
            default: return 'bg-gray-100 text-gray-800'
        }
    }

    if (loading) {
        return (
            <AppLayout title="Users" auth={auth}>
                <div className="flex items-center justify-center h-64">
                    <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600"></div>
                </div>
            </AppLayout>
        )
    }

    return (
        <AppLayout title="Users" auth={auth}>
            <div className="space-y-6">
                {/* Header */}
                <div>
                    <h1 className="text-xl lg:text-2xl font-bold text-gray-900">User Management</h1>
                    <p className="text-sm lg:text-base text-gray-600 mt-1">Manage system users and their permissions.</p>
                </div>

                {/* Filters and Add User */}
                <div className="bg-white p-4 rounded-lg shadow-sm">
                    <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-4">
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">Search</label>
                            <input
                                type="text"
                                placeholder="Search users..."
                                value={searchTerm}
                                onChange={(e) => setSearchTerm(e.target.value)}
                                className="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
                            />
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">Role</label>
                            <select
                                value={roleFilter}
                                onChange={(e) => setRoleFilter(e.target.value)}
                                className="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
                            >
                                <option value="">All Roles</option>
                                {fixedRoles.map(role => (
                                    <option key={role.id} value={role.id}>{role.name}</option>
                                ))}
                            </select>
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">Department</label>
                            <select
                                value={departmentFilter}
                                onChange={(e) => setDepartmentFilter(e.target.value)}
                                className="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
                            >
                                <option value="">All Departments</option>
                                {departments.map(dept => (
                                    <option key={dept.id} value={dept.id}>{dept.name}</option>
                                ))}
                            </select>
                        </div>
                        <div className="flex items-end">
                            <button
                                onClick={() => {
                                    setEditingUser(null)
                                    setFormData({
                                        full_name: '',
                                        email: '',
                                        password: '',
                                        department_id: '',
                                        role_id: ''
                                    })
                                    setShowModal(true)
                                }}
                                className="w-full bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md font-medium"
                            >
                                Add User
                            </button>
                        </div>
                    </div>
                </div>

                {/* Users Table - Desktop */}
                <div className="hidden lg:block bg-white shadow-sm rounded-lg overflow-hidden">
                    <table className="min-w-full divide-y divide-gray-200">
                        <thead className="bg-gray-50">
                            <tr>
                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Name
                                </th>
                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Email
                                </th>
                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Role
                                </th>
                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Department
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
                            {filteredUsers.map((user) => (
                                <tr key={user.id} className="hover:bg-gray-50">
                                    <td className="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                        {user.full_name}
                                    </td>
                                    <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        {user.email}
                                    </td>
                                    <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        {user.role?.name || 'N/A'}
                                    </td>
                                    <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        {user.department?.name || 'N/A'}
                                    </td>
                                    <td className="px-6 py-4 whitespace-nowrap">
                                        <span className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${getStatusColor('Active')}`}>
                                            Active
                                        </span>
                                    </td>
                                    <td className="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <div className="flex space-x-2">
                                            <button
                                                onClick={() => handleEdit(user)}
                                                className="text-blue-600 hover:text-blue-900"
                                            >
                                                Edit
                                            </button>
                                            <button
                                                onClick={() => handleDelete(user.id)}
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

                {/* Users Cards - Mobile */}
                <div className="lg:hidden space-y-4">
                    {filteredUsers.map((user) => (
                        <div key={user.id} className="bg-white shadow-sm rounded-lg p-4 border border-gray-200">
                            <div className="flex items-start justify-between mb-3">
                                <div className="flex-1 min-w-0">
                                    <h3 className="text-sm font-medium text-gray-900 truncate">
                                        {user.full_name}
                                    </h3>
                                    <p className="text-xs text-gray-500 truncate">{user.email}</p>
                                </div>
                                <span className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${getStatusColor('Active')}`}>
                                    Active
                                </span>
                            </div>

                            <div className="space-y-2 text-sm">
                                <div className="flex justify-between">
                                    <span className="text-gray-500">Role:</span>
                                    <span className="text-gray-900">{user.role?.name || 'N/A'}</span>
                                </div>
                                <div className="flex justify-between">
                                    <span className="text-gray-500">Department:</span>
                                    <span className="text-gray-900">{user.department?.name || 'N/A'}</span>
                                </div>
                            </div>

                            <div className="mt-4 pt-3 border-t border-gray-200">
                                <div className="flex space-x-2">
                                    <button
                                        onClick={() => handleEdit(user)}
                                        className="flex-1 bg-blue-50 hover:bg-blue-100 text-blue-600 hover:text-blue-900 px-3 py-2 rounded-md text-xs font-medium text-center"
                                    >
                                        Edit
                                    </button>
                                    <button
                                        onClick={() => handleDelete(user.id)}
                                        className="flex-1 bg-red-50 hover:bg-red-100 text-red-600 hover:text-red-900 px-3 py-2 rounded-md text-xs font-medium text-center"
                                    >
                                        Delete
                                    </button>
                                </div>
                            </div>
                        </div>
                    ))}
                </div>

                {filteredUsers.length === 0 && (
                    <div className="text-center py-12">
                        <div className="text-gray-400 text-6xl mb-4">👥</div>
                        <h3 className="text-lg font-medium text-gray-900 mb-2">No users found</h3>
                        <p className="text-gray-500">
                            {searchTerm || roleFilter || departmentFilter
                                ? 'Try adjusting your search criteria'
                                : 'Get started by adding your first user'
                            }
                        </p>
                    </div>
                )}

                {/* User Modal */}
                {showModal && (
                    <div className="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
                        <div className="relative top-20 mx-auto p-5 border w-11/12 md:w-3/4 lg:w-1/2 shadow-lg rounded-md bg-white">
                            <div className="mt-3">
                                <h3 className="text-lg font-medium text-gray-900 mb-4">
                                    {editingUser ? 'Edit User' : 'Add New User'}
                                </h3>
                                <form onSubmit={handleSubmit} className="space-y-4">
                                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <div>
                                            <label className="block text-sm font-medium text-gray-700 mb-1">
                                                Full Name *
                                            </label>
                                            <input
                                                type="text"
                                                value={formData.full_name}
                                                onChange={(e) => setFormData(prev => ({ ...prev, full_name: e.target.value }))}
                                                className={`block w-full px-3 py-2 border rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm ${errors.full_name ? 'border-red-300' : 'border-gray-300'}`}
                                                required
                                            />
                                            {errors.full_name && <p className="mt-1 text-sm text-red-600">{errors.full_name[0]}</p>}
                                        </div>
                                        <div>
                                            <label className="block text-sm font-medium text-gray-700 mb-1">
                                                Email *
                                            </label>
                                            <input
                                                type="email"
                                                value={formData.email}
                                                onChange={(e) => setFormData(prev => ({ ...prev, email: e.target.value }))}
                                                className={`block w-full px-3 py-2 border rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm ${errors.email ? 'border-red-300' : 'border-gray-300'}`}
                                                required
                                            />
                                            {errors.email && <p className="mt-1 text-sm text-red-600">{errors.email[0]}</p>}
                                        </div>
                                        <div>
                                            <label className="block text-sm font-medium text-gray-700 mb-1">
                                                Password {editingUser ? '(leave blank to keep current)' : '*'}
                                            </label>
                                            <input
                                                type="password"
                                                value={formData.password}
                                                onChange={(e) => setFormData(prev => ({ ...prev, password: e.target.value }))}
                                                className={`block w-full px-3 py-2 border rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm ${errors.password ? 'border-red-300' : 'border-gray-300'}`}
                                                required={!editingUser}
                                            />
                                            {errors.password && <p className="mt-1 text-sm text-red-600">{errors.password[0]}</p>}
                                        </div>
                                        <div>
                                            <label className="block text-sm font-medium text-gray-700 mb-1">
                                                Department *
                                            </label>
                                            <select
                                                value={formData.department_id}
                                                onChange={(e) => setFormData(prev => ({ ...prev, department_id: e.target.value }))}
                                                className={`block w-full px-3 py-2 border rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm ${errors.department_id ? 'border-red-300' : 'border-gray-300'}`}
                                                required
                                            >
                                                <option value="">Select Department</option>
                                                {departments.map(dept => (
                                                    <option key={dept.id} value={dept.id}>{dept.name}</option>
                                                ))}
                                            </select>
                                            {errors.department_id && <p className="mt-1 text-sm text-red-600">{errors.department_id[0]}</p>}
                                        </div>
                                        <div>
                                            <label className="block text-sm font-medium text-gray-700 mb-1">
                                                Role *
                                            </label>
                                            <select
                                                value={formData.role_id}
                                                onChange={(e) => setFormData(prev => ({ ...prev, role_id: e.target.value }))}
                                                className={`block w-full px-3 py-2 border rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm ${errors.role_id ? 'border-red-300' : 'border-gray-300'}`}
                                                required
                                            >
                                                <option value="">Select Role</option>
                                                {fixedRoles.map(role => (
                                                    <option key={role.id} value={role.id}>{role.name}</option>
                                                ))}
                                            </select>
                                            {errors.role_id && <p className="mt-1 text-sm text-red-600">{errors.role_id[0]}</p>}
                                        </div>
                                    </div>


                                    <div className="flex justify-end space-x-3 pt-4">
                                        <button
                                            type="button"
                                            onClick={() => setShowModal(false)}
                                            className="px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50"
                                        >
                                            Cancel
                                        </button>
                                        <button
                                            type="submit"
                                            disabled={submitting}
                                            className="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-md text-sm font-medium disabled:opacity-50"
                                        >
                                            {submitting ? 'Saving...' : (editingUser ? 'Update User' : 'Create User')}
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
