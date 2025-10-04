import { Head, Link, useForm } from '@inertiajs/react'
import { useState, useEffect } from 'react'

export default function AppLayout({ children, title, auth = {} }) {
    const [showUserMenu, setShowUserMenu] = useState(false)
    const [sidebarOpen, setSidebarOpen] = useState(false)

    // Close sidebar on mobile when clicking outside
    useEffect(() => {
        const handleResize = () => {
            if (window.innerWidth >= 1024) {
                setSidebarOpen(false)
            }
        }
        window.addEventListener('resize', handleResize)
        return () => window.removeEventListener('resize', handleResize)
    }, [])

    const { post } = useForm()

    const handleLogout = () => {
        post(route('logout'))
    }

    const getNavigation = () => {
        const baseNavigation = [
            { name: 'Dashboard', href: '/', icon: '📊', current: title === 'Home' },
            { name: 'Requests', href: '/requests', icon: '📄', current: title === 'Requests' },
            { name: 'Leave Requests', href: '/leave-requests', icon: '📅', current: title === 'Leave Requests' || title?.includes('Leave Request') },
            { name: 'Delegations', href: '/delegations', icon: '🔄', current: title === 'Delegation Management' },
        ]

        // Add role-specific navigation
        if (auth.user?.role?.name === 'admin') {
            baseNavigation.push(
                { name: 'Users', href: '/admin/users', icon: '👥', current: title === 'Users' },
                { name: 'Settings', href: '/settings', icon: '⚙️', current: title === 'Settings' }
            )
        }
        return baseNavigation
    }

    const navigation = getNavigation()

    return (
        <div className="min-h-screen bg-gray-50">
            <Head title={title} />

            {/* Mobile sidebar overlay */}
            {sidebarOpen && (
                <div
                    className="fixed inset-0 z-40 modal-backdrop lg:hidden"
                    onClick={() => setSidebarOpen(false)}
                />
            )}

            {/* Sidebar */}
            <div className={`fixed inset-y-0 left-0 z-50 w-64 bg-white shadow-lg transform transition-transform duration-300 ease-in-out ${
                sidebarOpen ? 'translate-x-0' : '-translate-x-full'
            } lg:translate-x-0`}>
                <div className="flex flex-col h-full">
                    {/* Logo */}
                    <div className="flex items-center justify-center h-auto px-4 lg:px-6 border-b border-gray-200">
                        <div className="flex flex-col items-center py-4">
                            <img
                                src="/images/logo.png"
                                alt="Approval System Logo"
                                className="w-fit h-10 object-contain"
                            />
                            <span className="text-lg lg:text-xl font-bold text-gray-800">
                                Approval Workflow
                            </span>
                        </div>
                    </div>

                    {/* Navigation */}
                    <nav className="flex-1 px-2 lg:px-4 py-6 space-y-2">
                        {navigation.map((item) => (
                            <Link
                                key={item.name}
                                href={item.href}
                                onClick={() => setSidebarOpen(false)}
                                className={`flex items-center space-x-3 px-3 py-2 rounded-lg text-sm font-medium transition-colors ${
                                    item.current
                                        ? 'bg-gray-100 text-gray-900'
                                        : 'text-gray-600 hover:text-gray-900 hover:bg-gray-50'
                                }`}
                            >
                                <span className="text-lg">{item.icon}</span>
                                <span>{item.name}</span>
                            </Link>
                        ))}
                    </nav>

                    {/* Logout */}
                    <div className="px-2 lg:px-4 py-6 border-t border-gray-200">
                        <button
                            onClick={handleLogout}
                            className="flex items-center space-x-3 px-3 py-2 rounded-lg text-sm font-medium text-gray-600 hover:text-gray-900 hover:bg-gray-50 w-full"
                        >
                            <span className="text-lg">🚪</span>
                            <span>Sign out</span>
                        </button>
                    </div>
                </div>
            </div>

            {/* Main content */}
            <div className="lg:ml-64">
                {/* Header */}
                <header className="bg-blue-800 text-white shadow-sm">
                    <div className="flex items-center justify-between h-16 px-4 lg:px-6">
                        {/* Mobile menu button */}
                        <button
                            onClick={() => setSidebarOpen(!sidebarOpen)}
                            className="lg:hidden flex items-center justify-center w-10 h-10 rounded-md text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500"
                        >
                            <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 6h16M4 12h16M4 18h16" />
                            </svg>
                        </button>

                        <h1 className="text-lg lg:text-xl font-semibold truncate">{title}</h1>

                        {/* User dropdown */}
                        <div className="relative">
                            <button
                                onClick={() => setShowUserMenu(!showUserMenu)}
                                className="flex items-center space-x-2 text-white hover:text-gray-200 focus:outline-none focus:ring-2 focus:ring-blue-500 rounded-md p-2"
                            >
                                <span className="text-sm font-medium truncate max-w-32">
                                    {auth?.user?.full_name || 'User'}
                                </span>
                                <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 9l-7 7-7-7" />
                                </svg>
                            </button>

                            {/* User dropdown */}
                            {showUserMenu && (
                                <div className="absolute right-0 mt-2 w-48 bg-white rounded-lg shadow-lg border border-gray-200 z-50">
                                    <div className="py-1">
                                        <div className="px-4 py-2 border-b border-gray-200">
                                            <p className="text-sm font-medium text-gray-900 truncate">
                                                {auth?.user?.full_name || 'User'}
                                            </p>
                                            <p className="text-sm text-gray-500 truncate">
                                                {auth?.user?.email || ''}
                                            </p>
                                            <p className="text-xs text-gray-400">
                                                {auth?.user?.role?.name || ''}
                                            </p>
                                        </div>
                                        <Link
                                            href="/profile"
                                            className="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100"
                                        >
                                            Profile
                                        </Link>
                                        {auth.user?.role?.name === 'admin' && (
                                            <Link
                                                href="/settings"
                                                className="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100"
                                            >
                                                Settings
                                            </Link>
                                        )}
                                        <div className="border-t border-gray-200">
                                            <button
                                                onClick={handleLogout}
                                                className="block w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100"
                                            >
                                                Sign out
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            )}
                        </div>
                    </div>
                </header>

                {/* Page content */}
                <main className="p-4 lg:p-6">
                    {children}
                </main>
            </div>

            {/* Click outside handlers */}
            {showUserMenu && (
                <div
                    className="fixed inset-0 z-40"
                    onClick={() => setShowUserMenu(false)}
                />
            )}
        </div>
    )
}
