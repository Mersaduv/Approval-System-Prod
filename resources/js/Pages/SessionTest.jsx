import { Head, Link } from '@inertiajs/react'
import AppLayout from '../Layouts/AppLayout'
import { useState, useEffect } from 'react'
import axios from 'axios'

export default function SessionTest({ auth }) {
    const [testResults, setTestResults] = useState({})
    const [loading, setLoading] = useState(true)

    useEffect(() => {
        runTests()
    }, [])

    const runTests = async () => {
        const results = {}

        try {
            setLoading(true)

            // Test 1: Check current user
            console.log('Current user from auth:', auth.user)

            // Test 2: Test session API
            try {
                const sessionResponse = await axios.get('/api/test-session')
                results.session = {
                    success: true,
                    data: sessionResponse.data
                }
                console.log('Session test result:', sessionResponse.data)
            } catch (error) {
                results.session = {
                    success: false,
                    error: error.response?.data?.message || error.message,
                    status: error.response?.status
                }
                console.error('Session test error:', error)
            }

            // Test 3: Test specific request
            try {
                const requestResponse = await axios.get('/api/requests/60')
                results.request = {
                    success: true,
                    data: requestResponse.data
                }
                console.log('Request test result:', requestResponse.data)
            } catch (error) {
                results.request = {
                    success: false,
                    error: error.response?.data?.message || error.message,
                    status: error.response?.status
                }
                console.error('Request test error:', error)
            }

            // Test 4: Test with different request IDs
            results.requests = {}
            for (let id of [60, 61, 62, 63, 64, 65]) {
                try {
                    const response = await axios.get(`/api/requests/${id}`)
                    results.requests[id] = {
                        success: true,
                        data: response.data
                    }
                } catch (error) {
                    results.requests[id] = {
                        success: false,
                        error: error.response?.data?.message || error.message,
                        status: error.response?.status
                    }
                }
            }

        } catch (error) {
            console.error('Test error:', error)
            results.error = error.message
        } finally {
            setLoading(false)
            setTestResults(results)
        }
    }

    if (loading) {
        return (
            <AppLayout title="Session Test" auth={auth}>
                <div className="flex items-center justify-center h-64">
                    <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600"></div>
                </div>
            </AppLayout>
        )
    }

    return (
        <AppLayout title="Session Test" auth={auth}>
            <div className="space-y-6">
                <div>
                    <h1 className="text-2xl font-bold text-gray-900">Session Test</h1>
                    <p className="text-gray-600 mt-1">Testing session and authentication issues.</p>
                </div>

                {/* Current User Info */}
                <div className="bg-white rounded-lg shadow-sm p-6">
                    <h2 className="text-lg font-semibold text-gray-900 mb-4">Current User</h2>
                    <div className="bg-gray-100 p-4 rounded-lg">
                        <pre className="text-sm">{JSON.stringify(auth.user, null, 2)}</pre>
                    </div>
                </div>

                {/* Test Results */}
                <div className="bg-white rounded-lg shadow-sm p-6">
                    <h2 className="text-lg font-semibold text-gray-900 mb-4">Test Results</h2>

                    {/* Session Test */}
                    <div className="mb-4">
                        <h3 className="font-medium text-gray-900 mb-2">Session Test</h3>
                        <div className={`p-3 rounded-lg ${testResults.session?.success ? 'bg-green-100' : 'bg-red-100'}`}>
                            <p className={`text-sm ${testResults.session?.success ? 'text-green-800' : 'text-red-800'}`}>
                                {testResults.session?.success ? 'PASS' : 'FAIL'}
                            </p>
                            {testResults.session?.error && (
                                <p className="text-xs text-red-600 mt-1">
                                    Error: {testResults.session.error} (Status: {testResults.session.status})
                                </p>
                            )}
                        </div>
                    </div>

                    {/* Request Test */}
                    <div className="mb-4">
                        <h3 className="font-medium text-gray-900 mb-2">Request Test (ID: 60)</h3>
                        <div className={`p-3 rounded-lg ${testResults.request?.success ? 'bg-green-100' : 'bg-red-100'}`}>
                            <p className={`text-sm ${testResults.request?.success ? 'text-green-800' : 'text-red-800'}`}>
                                {testResults.request?.success ? 'PASS' : 'FAIL'}
                            </p>
                            {testResults.request?.error && (
                                <p className="text-xs text-red-600 mt-1">
                                    Error: {testResults.request.error} (Status: {testResults.request.status})
                                </p>
                            )}
                        </div>
                    </div>

                    {/* Multiple Requests Test */}
                    <div className="mb-4">
                        <h3 className="font-medium text-gray-900 mb-2">Multiple Requests Test</h3>
                        <div className="grid grid-cols-2 md:grid-cols-3 gap-2">
                            {Object.entries(testResults.requests || {}).map(([id, result]) => (
                                <div key={id} className={`p-2 rounded text-xs ${result.success ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'}`}>
                                    Request {id}: {result.success ? 'PASS' : 'FAIL'}
                                </div>
                            ))}
                        </div>
                    </div>
                </div>

                {/* Actions */}
                <div className="bg-white rounded-lg shadow-sm p-6">
                    <h2 className="text-lg font-semibold text-gray-900 mb-4">Actions</h2>
                    <div className="space-x-4">
                        <button
                            onClick={runTests}
                            className="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700"
                        >
                            Run Tests Again
                        </button>
                        <Link
                            href="/procurement/requests/60"
                            className="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 inline-block"
                        >
                            Test Request 60 Page
                        </Link>
                        <Link
                            href="/debug-auth"
                            className="bg-purple-600 text-white px-4 py-2 rounded-lg hover:bg-purple-700 inline-block"
                        >
                            Debug Auth Page
                        </Link>
                    </div>
                </div>

                {/* Raw Data */}
                <div className="bg-white rounded-lg shadow-sm p-6">
                    <h2 className="text-lg font-semibold text-gray-900 mb-4">Raw Test Data</h2>
                    <pre className="bg-gray-100 p-4 rounded-lg overflow-auto text-sm">
                        {JSON.stringify(testResults, null, 2)}
                    </pre>
                </div>
            </div>
        </AppLayout>
    )
}
