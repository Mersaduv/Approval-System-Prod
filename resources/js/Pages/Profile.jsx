import { Head, useForm, router } from '@inertiajs/react'
import AppLayout from '../Layouts/AppLayout'
import { useState, useEffect } from 'react'

export default function Profile({ auth, errors: serverErrors, success }) {
    const [showPasswordForm, setShowPasswordForm] = useState(false)
    const [showCurrentPassword, setShowCurrentPassword] = useState(false)
    const [showNewPassword, setShowNewPassword] = useState(false)
    const [showConfirmPassword, setShowConfirmPassword] = useState(false)
    const [loading, setLoading] = useState(false)
    const [message, setMessage] = useState('')
    const [error, setError] = useState('')
    const [currentPasswordValid, setCurrentPasswordValid] = useState(false)
    const [validatingCurrentPassword, setValidatingCurrentPassword] = useState(false)

    // Password strength calculation
    const getPasswordStrength = (password) => {
        if (!password) return { strength: 0, label: '', className: '' }

        let strength = 0
        if (password.length >= 8) strength++
        if (/[a-z]/.test(password)) strength++
        if (/[A-Z]/.test(password)) strength++
        if (/[0-9]/.test(password)) strength++
        if (/[^A-Za-z0-9]/.test(password)) strength++

        const strengthMap = {
            0: { label: '', className: '' },
            1: { label: 'Very Weak', className: 'password-strength-weak' },
            2: { label: 'Weak', className: 'password-strength-weak' },
            3: { label: 'Fair', className: 'password-strength-fair' },
            4: { label: 'Good', className: 'password-strength-good' },
            5: { label: 'Strong', className: 'password-strength-strong' }
        }

        return {
            strength: strength,
            label: strengthMap[strength].label,
            className: strengthMap[strength].className
        }
    }

    const { data, setData, post, processing, errors, reset } = useForm({
        full_name: auth.user?.full_name || '',
        email: auth.user?.email || '',
        current_password: '',
        password: '',
        password_confirmation: ''
    })

    // Validate current password function
    const validateCurrentPassword = async (password) => {
        if (!password || password.length < 3) {
            setCurrentPasswordValid(false)
            return
        }

        setValidatingCurrentPassword(true)
        try {
            const response = await fetch('/api/validate-current-password', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    'Accept': 'application/json'
                },
                credentials: 'same-origin', // Include cookies for session
                body: JSON.stringify({ current_password: password })
            })

            const result = await response.json()
            console.log('Validation response:', result)
            setCurrentPasswordValid(result.success && result.valid)
        } catch (error) {
            console.error('Error validating current password:', error)
            setCurrentPasswordValid(false)
        } finally {
            setValidatingCurrentPassword(false)
        }
    }

    // Debounced validation for current password
    useEffect(() => {
        const timeoutId = setTimeout(() => {
            if (data.current_password) {
                validateCurrentPassword(data.current_password)
            } else {
                setCurrentPasswordValid(false)
            }
        }, 500) // 500ms delay

        return () => clearTimeout(timeoutId)
    }, [data.current_password])


    const handleProfileUpdate = (e) => {
        e.preventDefault()
        setLoading(true)
        setError('')
        setMessage('')

        post('/profile/update', {
            onSuccess: () => {
                setMessage('Profile updated successfully!')
                setLoading(false)
            },
            onError: (errors) => {
                setError('Failed to update profile. Please check the errors below.')
                setLoading(false)
            }
        })
    }

    const handlePasswordUpdate = (e) => {
        e.preventDefault()

        // Clear all messages first
        setError('')
        setMessage('')

        // Check if new passwords match
        if (data.password !== data.password_confirmation) {
            setError('New password and confirmation do not match.')
            return
        }

        // Check if all required fields are filled
        if (!data.current_password || !data.password || !data.password_confirmation) {
            setError('Please fill in all required fields.')
            return
        }

        setLoading(true)

        console.log('Password update form data:', {
            current_password: data.current_password,
            password: data.password,
            password_confirmation: data.password_confirmation
        })

        post('/profile/password', {
            onStart: () => {
                console.log('Password update started')
                setLoading(true)
            },
            onSuccess: (page) => {
                console.log('Password update success response:', page)
                console.log('Page props:', page.props)
                console.log('Errors in props:', page.props?.errors)

                // Check if there are any errors in the response
                if (page.props?.errors && Object.keys(page.props.errors).length > 0) {
                    console.log('Errors found in success response:', page.props.errors)
                    setMessage('') // Clear success message
                    const firstError = Object.values(page.props.errors)[0]
                    setError(Array.isArray(firstError) ? firstError[0] : firstError)
                } else {
                    // Only show success message if no errors
                    console.log('No errors found, showing success message')
                    setMessage('Password updated successfully!')
                    setError('') // Clear any previous errors
                    setShowPasswordForm(false)
                    setShowCurrentPassword(false)
                    setShowNewPassword(false)
                    setShowConfirmPassword(false)
                    setData({
                        ...data,
                        current_password: '',
                        password: '',
                        password_confirmation: ''
                    })
                }
                setLoading(false)
            },
            onError: (errors) => {
                console.log('Password update error response:', errors)
                setMessage('') // Clear any previous success messages
                // Check if there's a specific error message from server
                if (errors && Object.keys(errors).length > 0) {
                    const firstError = Object.values(errors)[0]
                    setError(Array.isArray(firstError) ? firstError[0] : firstError)
                } else {
                    setError('Failed to update password. Please check the errors below.')
                }
                setLoading(false)
            },
            onFinish: () => {
                console.log('Password update finished')
                setLoading(false)
            }
        })
    }

    return (
        <AppLayout title="Profile" auth={auth}>
            <div className="max-w-2xl mx-auto">
                <div className="bg-white shadow-sm rounded-lg">
                    <div className="px-6 py-4 border-b border-gray-200">
                        <h1 className="text-xl font-semibold text-gray-900">Profile</h1>
                        <p className="text-sm text-gray-600 mt-1">Manage your account information and security settings.</p>
                    </div>

                    <div className="p-6 space-y-8">
                        {/* Success/Error Messages */}
                        {message && (
                            <div className="bg-green-50 border border-green-200 rounded-lg p-4">
                                <div className="flex">
                                    <div className="flex-shrink-0">
                                        <span className="text-green-400">✅</span>
                                    </div>
                                    <div className="ml-3">
                                        <p className="text-sm text-green-800">{message}</p>
                                    </div>
                                </div>
                            </div>
                        )}

                        {(error || serverErrors?.error || serverErrors?.current_password) && (
                            <div className="bg-red-50 border border-red-200 rounded-lg p-4">
                                <div className="flex">
                                    <div className="flex-shrink-0">
                                        <span className="text-red-400">⚠️</span>
                                    </div>
                                    <div className="ml-3">
                                        <p className="text-sm text-red-800">
                                            {error || serverErrors?.error || serverErrors?.current_password}
                                        </p>
                                    </div>
                                </div>
                            </div>
                        )}

                        {/* Profile Information Form */}
                        <form onSubmit={handleProfileUpdate} className="space-y-6">
                            <div>
                                <h2 className="text-lg font-medium text-gray-900 mb-4">Profile Information</h2>

                                <div className="grid grid-cols-1 gap-6">
                                    <div className="form-group">
                                        <label htmlFor="full_name" className="form-label required">
                                            Full Name
                                        </label>
                                        <input
                                            type="text"
                                            id="full_name"
                                            value={data.full_name}
                                            onChange={(e) => setData('full_name', e.target.value)}
                                            className={`form-input ${(errors.full_name || serverErrors?.full_name) ? 'error' : ''}`}
                                            placeholder="Enter your full name"
                                        />
                                        {(errors.full_name || serverErrors?.full_name) && (
                                            <div className="form-error">{errors.full_name || serverErrors?.full_name}</div>
                                        )}
                                    </div>

                                    <div className="form-group">
                                        <label htmlFor="email" className="form-label required">
                                            Email Address
                                        </label>
                                        <input
                                            type="email"
                                            id="email"
                                            value={data.email}
                                            onChange={(e) => setData('email', e.target.value)}
                                            className={`form-input ${(errors.email || serverErrors?.email) ? 'error' : ''}`}
                                            placeholder="Enter your email address"
                                        />
                                        {(errors.email || serverErrors?.email) && (
                                            <div className="form-error">{errors.email || serverErrors?.email}</div>
                                        )}
                                    </div>
                                </div>
                            </div>

                            <div className="flex justify-end">
                                <button
                                    type="submit"
                                    disabled={processing || loading}
                                    className="form-button form-button-primary"
                                >
                                    {processing || loading ? (
                                        <>
                                            <div className="loading-spinner"></div>
                                            Updating...
                                        </>
                                    ) : (
                                        'Update Profile'
                                    )}
                                </button>
                            </div>
                        </form>

                        {/* Password Change Section */}
                        <div className="border-t border-gray-200 pt-8">
                            <div className="flex items-center justify-between mb-4">
                                <h2 className="text-lg font-medium text-gray-900">Password</h2>
                                <button
                                    type="button"
                                    onClick={() => setShowPasswordForm(!showPasswordForm)}
                                    className="form-button form-button-secondary"
                                    style={{ padding: '8px 16px', fontSize: '14px' }}
                                >
                                    {showPasswordForm ? 'Cancel' : 'Change Password'}
                                </button>
                            </div>

                            {showPasswordForm ? (
                                <form onSubmit={handlePasswordUpdate} className="space-y-6">
                                    <div className="grid grid-cols-1 gap-6">
                                        <div className="form-group">
                                            <label htmlFor="current_password" className="form-label required">
                                                Current Password
                                            </label>
                                            <div className="password-input-wrapper">
                                                <input
                                                    type={showCurrentPassword ? "text" : "password"}
                                                    id="current_password"
                                                    value={data.current_password}
                                                    onChange={(e) => setData('current_password', e.target.value)}
                                                    className={`form-input ${(errors.current_password || serverErrors?.current_password) ? 'error' : ''}`}
                                                    placeholder="Enter your current password"
                                                />
                                                <button
                                                    type="button"
                                                    className="password-toggle"
                                                    onClick={() => setShowCurrentPassword(!showCurrentPassword)}
                                                >
                                                    {showCurrentPassword ? 'Hide' : 'Show'}
                                                </button>
                                            </div>
                                            {data.current_password && (
                                                <div className="mt-2">
                                                    {validatingCurrentPassword ? (
                                                        <div className="flex items-center text-sm text-blue-600">
                                                            <div className="loading-spinner mr-2"></div>
                                                            Validating current password...
                                                        </div>
                                                    ) : currentPasswordValid ? (
                                                        <div className="flex items-center text-sm text-green-600">
                                                            <svg className="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                                                <path fillRule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clipRule="evenodd" />
                                                            </svg>
                                                            Current password is correct
                                                        </div>
                                                    ) : (
                                                        <div className="flex items-center text-sm text-red-600">
                                                            <svg className="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                                                <path fillRule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clipRule="evenodd" />
                                                            </svg>
                                                            Current password is incorrect
                                                        </div>
                                                    )}
                                                </div>
                                            )}
                                            {(errors.current_password || serverErrors?.current_password) && (
                                                <div className="form-error">{errors.current_password || serverErrors?.current_password}</div>
                                            )}
                                        </div>

                                        <div className="form-group">
                                            <label htmlFor="password" className="form-label required">
                                                New Password
                                            </label>
                                            <div className="password-input-wrapper">
                                                <input
                                                    type={showNewPassword ? "text" : "password"}
                                                    id="password"
                                                    value={data.password}
                                                    onChange={(e) => setData('password', e.target.value)}
                                                    className={`form-input ${(errors.password || serverErrors?.password) ? 'error' : ''}`}
                                                    placeholder="Enter your new password"
                                                />
                                                <button
                                                    type="button"
                                                    className="password-toggle"
                                                    onClick={() => setShowNewPassword(!showNewPassword)}
                                                >
                                                    {showNewPassword ? 'Hide' : 'Show'}
                                                </button>
                                            </div>
                                            {data.password && (
                                                <div className="password-strength">
                                                    <div className={`password-strength-bar ${getPasswordStrength(data.password).className}`}></div>
                                                </div>
                                            )}
                                            {data.password && getPasswordStrength(data.password).label && (
                                                <div className="text-xs text-gray-500 mt-1">
                                                    Password strength: <span className="font-medium">{getPasswordStrength(data.password).label}</span>
                                                </div>
                                            )}
                                            {(errors.password || serverErrors?.password) && (
                                                <div className="form-error">{errors.password || serverErrors?.password}</div>
                                            )}
                                        </div>

                                        <div className="form-group">
                                            <label htmlFor="password_confirmation" className="form-label required">
                                                Confirm New Password
                                            </label>
                                            <div className="password-input-wrapper">
                                                <input
                                                    type={showConfirmPassword ? "text" : "password"}
                                                    id="password_confirmation"
                                                    value={data.password_confirmation}
                                                    onChange={(e) => setData('password_confirmation', e.target.value)}
                                                    className={`form-input ${(errors.password_confirmation || serverErrors?.password_confirmation) ? 'error' : ''}`}
                                                    placeholder="Confirm your new password"
                                                />
                                                <button
                                                    type="button"
                                                    className="password-toggle"
                                                    onClick={() => setShowConfirmPassword(!showConfirmPassword)}
                                                >
                                                    {showConfirmPassword ? 'Hide' : 'Show'}
                                                </button>
                                            </div>
                                            {data.password_confirmation && data.password && (
                                                <div className={`text-xs mt-1 ${data.password === data.password_confirmation ? 'text-green-600' : 'text-red-600'}`}>
                                                    {data.password === data.password_confirmation ? '✅ Passwords match' : '❌ Passwords do not match'}
                                                </div>
                                            )}
                                            {(errors.password_confirmation || serverErrors?.password_confirmation) && (
                                                <div className="form-error">{errors.password_confirmation || serverErrors?.password_confirmation}</div>
                                            )}
                                        </div>
                                    </div>

                                    <div className="flex justify-end space-x-3">
                                        <button
                                            type="button"
                                            onClick={() => {
                                                setShowPasswordForm(false)
                                                setShowCurrentPassword(false)
                                                setShowNewPassword(false)
                                                setShowConfirmPassword(false)
                                                setData({
                                                    ...data,
                                                    current_password: '',
                                                    password: '',
                                                    password_confirmation: ''
                                                })
                                            }}
                                            className="form-button form-button-secondary"
                                        >
                                            Cancel
                                        </button>
                                        <button
                                            type="submit"
                                            disabled={processing || loading || !data.current_password || !data.password || !data.password_confirmation || data.password !== data.password_confirmation || !currentPasswordValid || validatingCurrentPassword}
                                            className={`form-button ${!data.current_password || !data.password || !data.password_confirmation || data.password !== data.password_confirmation || !currentPasswordValid || validatingCurrentPassword ? 'form-button-secondary opacity-50 cursor-not-allowed' : 'form-button-primary'}`}
                                        >
                                            {processing || loading ? (
                                                <>
                                                    <div className="loading-spinner"></div>
                                                    Updating...
                                                </>
                                            ) : validatingCurrentPassword ? (
                                                <>
                                                    <div className="loading-spinner"></div>
                                                    Validating...
                                                </>
                                            ) : !data.current_password || !data.password || !data.password_confirmation ? (
                                                'Fill All Fields'
                                            ) : !currentPasswordValid ? (
                                                'Invalid Current Password'
                                            ) : data.password !== data.password_confirmation ? (
                                                'Passwords Do Not Match'
                                            ) : (
                                                'Update Password'
                                            )}
                                        </button>
                                    </div>
                                </form>
                            ) : (
                                <div className="text-sm text-gray-500">
                                    Click "Change Password" to update your password.
                                </div>
                            )}
                        </div>

                        {/* Account Information */}
                        <div className="border-t border-gray-200 pt-8">
                            <h2 className="text-lg font-medium text-gray-900 mb-4">Account Information</h2>
                            <div className="grid grid-cols-1 gap-4">
                                <div>
                                    <dt className="text-sm font-medium text-gray-500">Role</dt>
                                    <dd className="mt-1 text-sm text-gray-900">{auth.user?.role?.name || 'N/A'}</dd>
                                </div>
                                <div>
                                    <dt className="text-sm font-medium text-gray-500">Department</dt>
                                    <dd className="mt-1 text-sm text-gray-900">{auth.user?.department?.name || 'N/A'}</dd>
                                </div>
                                <div>
                                    <dt className="text-sm font-medium text-gray-500">Member Since</dt>
                                    <dd className="mt-1 text-sm text-gray-900">
                                        {auth.user?.created_at ? new Date(auth.user.created_at).toLocaleDateString() : 'N/A'}
                                    </dd>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </AppLayout>
    )
}
