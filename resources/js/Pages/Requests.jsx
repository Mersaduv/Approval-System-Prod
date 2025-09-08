import { Head, Link } from '@inertiajs/react'
import AppLayout from '../Layouts/AppLayout'
import { useState, useEffect } from 'react'

export default function Requests() {
    const [requests, setRequests] = useState([])
    const [loading, setLoading] = useState(true)
    const [searchTerm, setSearchTerm] = useState('')

    useEffect(() => {
        // Simulate API call - in real app, this would fetch from API
        setTimeout(() => {
            setRequests([
                {
                    id: 1,
                    item: 'Office Chair',
                    amount: 150,
                    status: 'Pending',
                    employee: 'John Smith'
                },
                {
                    id: 2,
                    item: 'Monitor',
                    amount: 300,
                    status: 'Approved',
                    employee: 'Jane Doe'
                },
                {
                    id: 3,
                    item: 'Network Cable',
                    amount: 20,
                    status: 'Rejected',
                    employee: 'Sam Johnson'
                },
                {
                    id: 4,
                    item: 'Desk',
                    amount: 250,
                    status: 'Pending',
                    employee: 'Linda Davis'
                }
            ])
            setLoading(false)
        }, 1000)
    }, [])

    const filteredRequests = requests.filter(request => {
        const matchesSearch = request.item.toLowerCase().includes(searchTerm.toLowerCase()) ||
                            request.employee.toLowerCase().includes(searchTerm.toLowerCase())
        return matchesSearch
    })

    const getStatusColor = (status) => {
        switch (status.toLowerCase()) {
            case 'pending': return 'bg-yellow-100 text-yellow-800'
            case 'approved': return 'bg-green-100 text-green-800'
            case 'rejected': return 'bg-red-100 text-red-800'
            default: return 'bg-gray-100 text-gray-800'
        }
    }

    if (loading) {
        return (
            <AppLayout title="Requests">
                <div className="flex items-center justify-center h-64">
                    <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600"></div>
                </div>
            </AppLayout>
        )
    }

    return (
        <AppLayout title="Requests">
            <div className="space-y-6">
                {/* Search and New Request */}
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
                                placeholder="Search"
                                value={searchTerm}
                                onChange={(e) => setSearchTerm(e.target.value)}
                                className="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-md leading-5 bg-white placeholder-gray-500 focus:outline-none focus:placeholder-gray-400 focus:ring-1 focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
                            />
                        </div>
                    </div>
                    <Link
                        href="/requests/new"
                        className="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md font-medium"
                    >
                        New Request
                    </Link>
                </div>

                {/* Requests Table */}
                <div className="bg-white shadow-sm rounded-lg overflow-hidden">
                    <table className="min-w-full divide-y divide-gray-200">
                        <thead className="bg-gray-50">
                            <tr>
                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    ID
                                </th>
                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Employee
                                </th>
                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Item
                                </th>
                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Amount
                                </th>
                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Status
                                </th>
                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Action
                                </th>
                            </tr>
                        </thead>
                        <tbody className="bg-white divide-y divide-gray-200">
                            {filteredRequests.map((request) => (
                                <tr key={request.id} className="hover:bg-gray-50">
                                    <td className="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                        {request.id}
                                    </td>
                                    <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        {request.employee}
                                    </td>
                                    <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        {request.item}
                                    </td>
                                    <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        ${request.amount}
                                    </td>
                                    <td className="px-6 py-4 whitespace-nowrap">
                                        <span className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${getStatusColor(request.status)}`}>
                                            {request.status}
                                        </span>
                                    </td>
                                    <td className="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        {request.status === 'Pending' ? (
                                            <button className="text-blue-600 hover:text-blue-900 bg-blue-50 hover:bg-blue-100 px-3 py-1 rounded-md">
                                                Approve
                                            </button>
                                        ) : request.status === 'Approved' ? (
                                            <button className="text-red-600 hover:text-red-900 bg-red-50 hover:bg-red-100 px-3 py-1 rounded-md">
                                                Reject
                                            </button>
                                        ) : (
                                            <button className="text-blue-600 hover:text-blue-900 bg-blue-50 hover:bg-blue-100 px-3 py-1 rounded-md">
                                                Approve
                                            </button>
                                        )}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                {filteredRequests.length === 0 && (
                    <div className="text-center py-12">
                        <div className="text-gray-400 text-6xl mb-4">📄</div>
                        <h3 className="text-lg font-medium text-gray-900 mb-2">No requests found</h3>
                        <p className="text-gray-500">
                            {searchTerm
                                ? 'Try adjusting your search criteria'
                                : 'Get started by creating your first request'
                            }
                        </p>
                    </div>
                )}
            </div>
        </AppLayout>
    )
}
