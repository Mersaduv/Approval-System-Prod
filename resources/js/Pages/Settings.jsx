import { Head, Link } from '@inertiajs/react'
import AppLayout from '../Layouts/AppLayout'
import { useState } from 'react'

export default function Settings() {
    const [settings, setSettings] = useState({
        emailNotifications: true,
        smsNotifications: false,
        autoApproval: false,
        approvalThreshold: 1000,
        workingHours: '9:00 AM - 5:00 PM',
        timezone: 'UTC+4'
    })

    const handleChange = (e) => {
        const { name, value, type, checked } = e.target
        setSettings(prev => ({
            ...prev,
            [name]: type === 'checkbox' ? checked : value
        }))
    }

    const handleSubmit = (e) => {
        e.preventDefault()
        // In real app, this would save to API
        console.log('Saving settings:', settings)
        alert('Settings saved successfully!')
    }

    return (
        <AppLayout title="Settings">
            <div className="space-y-6">
                {/* Header */}
                <div>
                    <h1 className="text-2xl font-bold text-gray-900">Settings</h1>
                    <p className="text-gray-600 mt-1">Manage your application settings and preferences.</p>
                </div>

                {/* Settings Form */}
                <div className="bg-white shadow-sm rounded-lg overflow-hidden">
                    <form onSubmit={handleSubmit} className="p-6 space-y-6">
                        {/* Notification Settings */}
                        <div>
                            <h3 className="text-lg font-medium text-gray-900 mb-4">Notification Settings</h3>
                            <div className="space-y-4">
                                <div className="flex items-center">
                                    <input
                                        id="emailNotifications"
                                        name="emailNotifications"
                                        type="checkbox"
                                        checked={settings.emailNotifications}
                                        onChange={handleChange}
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
                                        onChange={handleChange}
                                        className="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
                                    />
                                    <label htmlFor="smsNotifications" className="ml-2 block text-sm text-gray-900">
                                        SMS notifications
                                    </label>
                                </div>
                            </div>
                        </div>

                        {/* Approval Settings */}
                        <div>
                            <h3 className="text-lg font-medium text-gray-900 mb-4">Approval Settings</h3>
                            <div className="space-y-4">
                                <div className="flex items-center">
                                    <input
                                        id="autoApproval"
                                        name="autoApproval"
                                        type="checkbox"
                                        checked={settings.autoApproval}
                                        onChange={handleChange}
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
                                        onChange={handleChange}
                                        className="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
                                    />
                                </div>
                            </div>
                        </div>

                        {/* System Settings */}
                        <div>
                            <h3 className="text-lg font-medium text-gray-900 mb-4">System Settings</h3>
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
                                        onChange={handleChange}
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
                                        onChange={handleChange}
                                        className="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
                                    >
                                        <option value="UTC+4">UTC+4 (Kabul)</option>
                                        <option value="UTC+5">UTC+5 (Karachi)</option>
                                        <option value="UTC+0">UTC+0 (London)</option>
                                        <option value="UTC-5">UTC-5 (New York)</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        {/* Save Button */}
                        <div className="flex justify-end pt-6 border-t border-gray-200">
                            <button
                                type="submit"
                                className="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-md font-medium"
                            >
                                Save Settings
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </AppLayout>
    )
}
