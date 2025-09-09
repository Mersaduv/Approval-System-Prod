import { Head, Link } from '@inertiajs/react'
import AppLayout from '../Layouts/AppLayout'
import { useState, useEffect } from 'react'

export default function Notifications({ auth }) {
    const [notifications, setNotifications] = useState([])
    const [loading, setLoading] = useState(true)
    const [unreadCount, setUnreadCount] = useState(0)

    useEffect(() => {
        // Simulate API call - in real app, this would fetch from API
        setTimeout(() => {
            setNotifications([
                {
                    id: 1,
                    title: 'Approval Required',
                    message: 'You have a pending approval request for Office Supplies',
                    type: 'approval',
                    status: 'unread',
                    created_at: '2024-01-15T10:30:00Z',
                    request_id: 1
                },
                {
                    id: 2,
                    title: 'Request Approved',
                    message: 'Your request for Laptop Computer has been approved',
                    type: 'success',
                    status: 'read',
                    created_at: '2024-01-14T15:45:00Z',
                    request_id: 2
                },
                {
                    id: 3,
                    title: 'Request Rejected',
                    message: 'Your request for Conference Room Equipment has been rejected',
                    type: 'error',
                    status: 'read',
                    created_at: '2024-01-13T09:20:00Z',
                    request_id: 3
                },
                {
                    id: 4,
                    title: 'Request Delivered',
                    message: 'Your request for Office Supplies has been delivered',
                    type: 'info',
                    status: 'unread',
                    created_at: '2024-01-12T14:15:00Z',
                    request_id: 4
                }
            ])
            setUnreadCount(2)
            setLoading(false)
        }, 1000)
    }, [])

    const markAsRead = (notificationId) => {
        setNotifications(prev =>
            prev.map(notif =>
                notif.id === notificationId
                    ? { ...notif, status: 'read' }
                    : notif
            )
        )
        setUnreadCount(prev => Math.max(0, prev - 1))
    }

    const markAllAsRead = () => {
        setNotifications(prev =>
            prev.map(notif => ({ ...notif, status: 'read' }))
        )
        setUnreadCount(0)
    }

    const deleteNotification = (notificationId) => {
        setNotifications(prev => prev.filter(notif => notif.id !== notificationId))
    }

    const getNotificationIcon = (type) => {
        switch (type) {
            case 'approval': return '🔔'
            case 'success': return '✅'
            case 'error': return '❌'
            case 'info': return 'ℹ️'
            default: return '📢'
        }
    }

    const getNotificationColor = (type) => {
        switch (type) {
            case 'approval': return 'border-l-yellow-500 bg-yellow-50'
            case 'success': return 'border-l-green-500 bg-green-50'
            case 'error': return 'border-l-red-500 bg-red-50'
            case 'info': return 'border-l-blue-500 bg-blue-50'
            default: return 'border-l-gray-500 bg-gray-50'
        }
    }

    const formatTimeAgo = (dateString) => {
        const date = new Date(dateString)
        const now = new Date()
        const diffInMinutes = Math.floor((now - date) / (1000 * 60))

        if (diffInMinutes < 1) return 'Just now'
        if (diffInMinutes < 60) return `${diffInMinutes}m ago`
        if (diffInMinutes < 1440) return `${Math.floor(diffInMinutes / 60)}h ago`
        return `${Math.floor(diffInMinutes / 1440)}d ago`
    }

    if (loading) {
        return (
            <AppLayout title="Notifications" auth={auth}>
                <div className="flex items-center justify-center h-64">
                    <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600"></div>
                </div>
            </AppLayout>
        )
    }

    return (
        <AppLayout title="Notifications" auth={auth}>
            <div className="max-w-4xl mx-auto">
                {/* Header */}
                <div className="flex justify-between items-center mb-8">
                    <div>
                        <h1 className="text-3xl font-bold text-gray-900">Notifications</h1>
                        <p className="text-gray-600 mt-2">
                            {unreadCount > 0
                                ? `${unreadCount} unread notification${unreadCount > 1 ? 's' : ''}`
                                : 'All caught up!'
                            }
                        </p>
                    </div>
                    {unreadCount > 0 && (
                        <button
                            onClick={markAllAsRead}
                            className="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md font-medium"
                        >
                            Mark All as Read
                        </button>
                    )}
                </div>

                {/* Notifications List */}
                <div className="space-y-4">
                    {notifications.map((notification) => (
                        <div
                            key={notification.id}
                            className={`bg-white rounded-lg shadow-sm border-l-4 ${getNotificationColor(notification.type)} ${
                                notification.status === 'unread' ? 'ring-2 ring-blue-200' : ''
                            }`}
                        >
                            <div className="p-6">
                                <div className="flex items-start justify-between">
                                    <div className="flex items-start space-x-3">
                                        <div className="flex-shrink-0">
                                            <span className="text-2xl">
                                                {getNotificationIcon(notification.type)}
                                            </span>
                                        </div>
                                        <div className="flex-1 min-w-0">
                                            <div className="flex items-center space-x-2">
                                                <h3 className={`text-sm font-medium ${
                                                    notification.status === 'unread'
                                                        ? 'text-gray-900'
                                                        : 'text-gray-700'
                                                }`}>
                                                    {notification.title}
                                                </h3>
                                                {notification.status === 'unread' && (
                                                    <span className="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                                        New
                                                    </span>
                                                )}
                                            </div>
                                            <p className={`mt-1 text-sm ${
                                                notification.status === 'unread'
                                                    ? 'text-gray-900'
                                                    : 'text-gray-600'
                                            }`}>
                                                {notification.message}
                                            </p>
                                            <div className="mt-2 flex items-center space-x-4 text-xs text-gray-500">
                                                <span>{formatTimeAgo(notification.created_at)}</span>
                                                {notification.request_id && (
                                                    <Link
                                                        href={`/requests/${notification.request_id}`}
                                                        className="text-blue-600 hover:text-blue-800"
                                                    >
                                                        View Request
                                                    </Link>
                                                )}
                                            </div>
                                        </div>
                                    </div>
                                    <div className="flex items-center space-x-2">
                                        {notification.status === 'unread' && (
                                            <button
                                                onClick={() => markAsRead(notification.id)}
                                                className="text-gray-400 hover:text-gray-600"
                                                title="Mark as read"
                                            >
                                                <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
                                                </svg>
                                            </button>
                                        )}
                                        <button
                                            onClick={() => deleteNotification(notification.id)}
                                            className="text-gray-400 hover:text-red-600"
                                            title="Delete notification"
                                        >
                                            <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                            </svg>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    ))}
                </div>

                {notifications.length === 0 && (
                    <div className="text-center py-12">
                        <div className="text-gray-400 text-6xl mb-4">🔔</div>
                        <h3 className="text-lg font-medium text-gray-900 mb-2">No notifications</h3>
                        <p className="text-gray-500">
                            You're all caught up! New notifications will appear here.
                        </p>
                    </div>
                )}
            </div>
        </AppLayout>
    )
}
