import { Head, Link } from '@inertiajs/react'
import AppLayout from '../Layouts/AppLayout'
import { useState, useEffect } from 'react'

export default function Users() {
    const [users, setUsers] = useState([])
    const [loading, setLoading] = useState(true)
    const [searchTerm, setSearchTerm] = useState('')

    useEffect(() => {
        // Simulate API call - in real app, this would fetch from API
        setTimeout(() => {
            setUsers([
                {
                    id: 1,
                    name: 'John Smith',
                    email: 'john.smith@company.com',
                    role: 'Manager',
                    department: 'IT',
                    status: 'Active'
                },
                {
                    id: 2,
                    name: 'Jane Doe',
                    email: 'jane.doe@company.com',
                    role: 'Employee',
                    department: 'HR',
                    status: 'Active'
                },
                {
                    id: 3,
                    name: 'Mike Johnson',
                    email: 'mike.johnson@company.com',
                    role: 'Admin',
                    department: 'Operations',
                    status: 'Active'
                },
                {
                    id: 4,
                    name: 'Sarah Wilson',
                    email: 'sarah.wilson@company.com',
                    role: 'Employee',
                    department: 'Finance',
                    status: 'Inactive'
                }
            ])
            setLoading(false)
        }, 1000)
    }, [])

    const filteredUsers = users.filter(user => {
        const matchesSearch = user.name.toLowerCase().includes(searchTerm.toLowerCase()) ||
                            user.email.toLowerCase().includes(searchTerm.toLowerCase()) ||
                            user.role.toLowerCase().includes(searchTerm.toLowerCase())
        return matchesSearch
    })

    const getStatusColor = (status) => {
        switch (status.toLowerCase()) {
            case 'active': return 'bg-green-100 text-green-800'
            case 'inactive': return 'bg-red-100 text-red-800'
            default: return 'bg-gray-100 text-gray-800'
        }
    }

    if (loading) {
        return (
            <AppLayout title="Users">
                <div className="flex items-center justify-center h-64">
                    <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600"></div>
                </div>
            </AppLayout>
        )
    }

    return (
        <AppLayout title="Users">
            <div className="space-y-6">
                {/* Search and Add User */}
                <div className="flex items-center justify-between">
                    <div className="flex-1 max-w-md">
                        <div className="relative">
                            <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <svg className="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                </svg>
                            </div>
                            <input
                                type="text"
                                placeholder="Search users..."
                                value={searchTerm}
                                onChange={(e) => setSearchTerm(e.target.value)}
                                className="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-md leading-5 bg-white placeholder-gray-500 focus:outline-none focus:placeholder-gray-400 focus:ring-1 focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
                            />
                        </div>
                    </div>
                    <button className="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md font-medium">
                        Add User
                    </button>
                </div>

                {/* Users Table */}
                <div className="bg-white shadow-sm rounded-lg overflow-hidden">
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
                                        {user.name}
                                    </td>
                                    <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        {user.email}
                                    </td>
                                    <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        {user.role}
                                    </td>
                                    <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        {user.department}
                                    </td>
                                    <td className="px-6 py-4 whitespace-nowrap">
                                        <span className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${getStatusColor(user.status)}`}>
                                            {user.status}
                                        </span>
                                    </td>
                                    <td className="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <div className="flex space-x-2">
                                            <button className="text-blue-600 hover:text-blue-900">
                                                Edit
                                            </button>
                                            <button className="text-red-600 hover:text-red-900">
                                                Delete
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                {filteredUsers.length === 0 && (
                    <div className="text-center py-12">
                        <div className="text-gray-400 text-6xl mb-4">👥</div>
                        <h3 className="text-lg font-medium text-gray-900 mb-2">No users found</h3>
                        <p className="text-gray-500">
                            {searchTerm
                                ? 'Try adjusting your search criteria'
                                : 'Get started by adding your first user'
                            }
                        </p>
                    </div>
                )}
            </div>
        </AppLayout>
    )
}
