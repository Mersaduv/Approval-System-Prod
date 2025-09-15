import { Head, Link } from '@inertiajs/react'
import AppLayout from '../Layouts/AppLayout'
import { useState, useEffect } from 'react'
import axios from 'axios'

export default function DebugAuth({ auth }) {
    const [debugInfo, setDebugInfo] = useState({})
    const [loading, setLoading] = useState(true)
    const [testResults, setTestResults] = useState({})

    useEffect(() => {
        fetchDebugInfo()
    }, [])

    const fetchDebugInfo = async () => {
        try {
            setLoading(true)

            // Test 1: Get current user
            const userResponse = await axios.get('/api/user')
            console.log('User response:', userResponse.data)

            // Test 2: Test session
            const sessionResponse = await axios.get('/api/test-session')
            console.log('Session response:', sessionResponse.data)

            // Test 3: Test specific request
            const requestResponse = await axios.get('/api/requests/60')
            console.log('Request response:', requestResponse.data)

            setDebugInfo({
                user: userResponse.data,
                session: sessionResponse.data,
                request: requestResponse.data
            })

            setTestResults({
                userTest: userResponse.data.success ? 'PASS' : 'FAIL',
                sessionTest: sessionResponse.data.success ? 'PASS' : 'FAIL',
                requestTest: requestResponse.data.success ? 'PASS' : 'FAIL'
            })

        } catch (error) {
            console.error('Debug error:', error)
            setDebugInfo({ error: error.message })
        } finally {
            setLoading(false)
        }
    }

    const testSpecificRequest = async (requestId) => {
        try {
            const response = await axios.get(`/api/requests/${requestId}`)
            return {
                success: true,
                data: response.data
            }
        } catch (error) {
            return {
                success: false,
                error: error.response?.data?.message || error.message,
                status: error.response?.status
            }
        }
    }

    if (loading) {
        return (
            <AppLayout title="Debug Auth" auth={auth}>
                <div className="flex items-center justify-center h-64">
                    <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600"></div>
                </div>
            </AppLayout>
        )
    }

    return (
        <AppLayout title="Debug Authentication" auth={auth}>
            <div className="space-y-6">
                <div>
                    <h1 className="text-2xl font-bold text-gray-900">Debug Authentication</h1>
                    <p className="text-gray-600 mt-1">Debug information for authentication issues.</p>
                </div>

                {/* Test Results */}
                <div className="bg-white rounded-lg shadow-sm p-6">
                    <h2 className="text-lg font-semibold text-gray-900 mb-4">Test Results</h2>
                    <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div className={`p-4 rounded-lg ${testResults.userTest === 'PASS' ? 'bg-green-100' : 'bg-red-100'}`}>
                            <h3 className="font-medium">User API</h3>
                            <p className={`text-sm ${testResults.userTest === 'PASS' ? 'text-green-800' : 'text-red-800'}`}>
                                {testResults.userTest}
                            </p>
                        </div>
                        <div className={`p-4 rounded-lg ${testResults.sessionTest === 'PASS' ? 'bg-green-100' : 'bg-red-100'}`}>
                            <h3 className="font-medium">Session API</h3>
                            <p className={`text-sm ${testResults.sessionTest === 'PASS' ? 'text-green-800' : 'text-red-800'}`}>
                                {testResults.sessionTest}
                            </p>
                        </div>
                        <div className={`p-4 rounded-lg ${testResults.requestTest === 'PASS' ? 'bg-green-100' : 'bg-red-100'}`}>
                            <h3 className="font-medium">Request API</h3>
                            <p className={`text-sm ${testResults.requestTest === 'PASS' ? 'text-green-800' : 'text-red-800'}`}>
                                {testResults.requestTest}
                            </p>
                        </div>
                    </div>
                </div>

                {/* Debug Information */}
                <div className="bg-white rounded-lg shadow-sm p-6">
                    <h2 className="text-lg font-semibold text-gray-900 mb-4">Debug Information</h2>
                    <pre className="bg-gray-100 p-4 rounded-lg overflow-auto text-sm">
                        {JSON.stringify(debugInfo, null, 2)}
                    </pre>
                </div>

                {/* Test Specific Requests */}
                <div className="bg-white rounded-lg shadow-sm p-6">
                    <h2 className="text-lg font-semibold text-gray-900 mb-4">Test Specific Requests</h2>
                    <div className="space-y-4">
                        {[60, 61, 62, 63, 64, 65].map(requestId => (
                            <RequestTest key={requestId} requestId={requestId} />
                        ))}
                    </div>
                </div>

                {/* Actions */}
                <div className="bg-white rounded-lg shadow-sm p-6">
                    <h2 className="text-lg font-semibold text-gray-900 mb-4">Actions</h2>
                    <div className="space-x-4">
                        <button
                            onClick={fetchDebugInfo}
                            className="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700"
                        >
                            Refresh Debug Info
                        </button>
                        <Link
                            href="/procurement/requests/60"
                            className="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 inline-block"
                        >
                            Test Request 60
                        </Link>
                    </div>
                </div>
            </div>
        </AppLayout>
    )
}

function RequestTest({ requestId }) {
    const [result, setResult] = useState(null)
    const [loading, setLoading] = useState(false)

    const testRequest = async () => {
        setLoading(true)
        try {
            const response = await axios.get(`/api/requests/${requestId}`)
            setResult({
                success: true,
                data: response.data
            })
        } catch (error) {
            setResult({
                success: false,
                error: error.response?.data?.message || error.message,
                status: error.response?.status
            })
        } finally {
            setLoading(false)
        }
    }

    return (
        <div className="border border-gray-200 rounded-lg p-4">
            <div className="flex items-center justify-between">
                <div>
                    <h3 className="font-medium">Request {requestId}</h3>
                    {result && (
                        <p className={`text-sm ${result.success ? 'text-green-600' : 'text-red-600'}`}>
                            {result.success ? 'Accessible' : `Error: ${result.error} (${result.status})`}
                        </p>
                    )}
                </div>
                <button
                    onClick={testRequest}
                    disabled={loading}
                    className="bg-blue-600 text-white px-3 py-1 rounded text-sm hover:bg-blue-700 disabled:opacity-50"
                >
                    {loading ? 'Testing...' : 'Test'}
                </button>
            </div>
        </div>
    )
}
