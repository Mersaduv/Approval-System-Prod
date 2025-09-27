import React, { useState, useEffect } from 'react';
import { Head, Link } from '@inertiajs/react';
import AppLayout from '../Layouts/AppLayout';
import axios from 'axios';
import AlertModal from '../Components/AlertModal';

// Configure axios for CSRF token
const token = document.head.querySelector('meta[name="csrf-token"]');
if (token) {
    axios.defaults.headers.common['X-CSRF-TOKEN'] = token.content;
}
axios.defaults.withCredentials = true;

export default function UserTrash({ auth = {} }) {
    const [users, setUsers] = useState([]);
    const [loading, setLoading] = useState(true);
    const [showRestoreModal, setShowRestoreModal] = useState(false);
    const [showDeleteModal, setShowDeleteModal] = useState(false);
    const [userToAction, setUserToAction] = useState(null);
    const [showAlert, setShowAlert] = useState(false);
    const [alertMessage, setAlertMessage] = useState('');
    const [alertType, setAlertType] = useState('info');
    const [restoring, setRestoring] = useState(false);
    const [deleting, setDeleting] = useState(false);

    useEffect(() => {
        fetchTrashedUsers();
    }, []);

    const fetchTrashedUsers = async () => {
        try {
            setLoading(true);
            const response = await axios.get('/api/admin/users/trash');

            if (response.data.success) {
                setUsers(response.data.data || []);
            } else {
                console.error('Error fetching users:', response.data.message);
                setAlertMessage('Error fetching users: ' + response.data.message);
                setAlertType('error');
                setShowAlert(true);
            }
        } catch (error) {
            console.error('Error fetching trashed users:', error);
            setAlertMessage('Error fetching users');
            setAlertType('error');
            setShowAlert(true);
        } finally {
            setLoading(false);
        }
    };

    const handleRestore = async (userId) => {
        try {
            setRestoring(true);
            const response = await axios.post(`/api/admin/users/${userId}/restore`);

            if (response.data.success) {
                // Remove user from list
                setUsers(users.filter(user => user.id !== userId));
                setShowRestoreModal(false);
                setUserToAction(null);

                // Show success message
                setAlertMessage('User restored successfully!');
                setAlertType('success');
                setShowAlert(true);
            } else {
                setAlertMessage('Error: ' + (response.data.message || 'Failed to restore user'));
                setAlertType('error');
                setShowAlert(true);
            }
        } catch (error) {
            console.error('Error restoring user:', error);
            setAlertMessage('Error restoring user');
            setAlertType('error');
            setShowAlert(true);
        } finally {
            setRestoring(false);
        }
    };

    const handleForceDelete = async (userId) => {
        try {
            setDeleting(true);
            const response = await axios.delete(`/api/admin/users/${userId}/force`);

            if (response.data.success) {
                // Remove user from list
                setUsers(users.filter(user => user.id !== userId));
                setShowDeleteModal(false);
                setUserToAction(null);

                // Show success message
                setAlertMessage('User permanently deleted!');
                setAlertType('success');
                setShowAlert(true);
            } else {
                setAlertMessage('Error: ' + (response.data.message || 'Failed to delete user'));
                setAlertType('error');
                setShowAlert(true);
            }
        } catch (error) {
            console.error('Error deleting user:', error);
            setAlertMessage('Error deleting user');
            setAlertType('error');
            setShowAlert(true);
        } finally {
            setDeleting(false);
        }
    };

    const openRestoreModal = (user) => {
        setUserToAction(user);
        setShowRestoreModal(true);
    };

    const openDeleteModal = (user) => {
        setUserToAction(user);
        setShowDeleteModal(true);
    };

    if (loading) {
        return (
            <AppLayout auth={auth} title="User Trash">
                <Head title="User Trash" />
                <div className="py-12">
                    <div className="max-w-7xl mx-auto sm:px-6 lg:px-8">
                        <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                            <div className="p-6 text-center">
                                <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-indigo-600 mx-auto"></div>
                                <p className="mt-4 text-gray-600">Loading trashed users...</p>
                            </div>
                        </div>
                    </div>
                </div>
            </AppLayout>
        );
    }

    return (
        <AppLayout auth={auth} title="User Trash">
            <Head title="User Trash" />

            <div className="py-12">
                <div className="max-w-7xl mx-auto sm:px-6 lg:px-8">
                    {/* Header */}
                    <div className="mb-8">
                        <div className="flex items-center justify-between">
                            <div>
                                <h1 className="text-3xl font-bold text-gray-900">
                                    User Trash
                                </h1>
                                <p className="mt-2 text-gray-600">
                                    Manage deleted users. You can restore them or permanently delete them.
                                </p>
                            </div>
                            <Link
                                href="/admin/users"
                                className="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                            >
                                Back to Users
                            </Link>
                        </div>
                    </div>

                    {/* Users Table */}
                    <div className="bg-white shadow overflow-hidden sm:rounded-md">
                        {users.length === 0 ? (
                            <div className="text-center py-12">
                                <h3 className="mt-2 text-sm font-medium text-gray-900">No deleted users</h3>
                                <p className="mt-1 text-sm text-gray-500">
                                    There are no users in the trash.
                                </p>
                            </div>
                        ) : (
                            <ul className="divide-y divide-gray-200">
                                {users.map((user) => (
                                    <li key={user.id} className="px-6 py-4">
                                        <div className="flex items-center justify-between">
                                            <div className="flex items-center">
                                                <div className="flex-shrink-0 h-10 w-10">
                                                    <div className="h-10 w-10 rounded-full bg-gray-300 flex items-center justify-center">
                                                        <span className="text-sm font-medium text-gray-700">
                                                            {user.full_name?.charAt(0) || '?'}
                                                        </span>
                                                    </div>
                                                </div>
                                                <div className="ml-4">
                                                    <div className="text-sm font-medium text-gray-900">
                                                        {user.full_name || 'Unknown User'}
                                                    </div>
                                                    <div className="text-sm text-gray-500">
                                                        {user.email || 'No email'}
                                                    </div>
                                                    <div className="text-xs text-gray-400">
                                                        Deleted on: {new Date(user.deleted_at).toLocaleDateString()}
                                                    </div>
                                                </div>
                                            </div>
                                            <div className="flex items-center space-x-2">
                                                <div className="text-sm text-gray-500">
                                                    <div>Role: {user.role?.name || 'Unknown'}</div>
                                                    <div>Department: {user.department?.name || 'Unknown'}</div>
                                                </div>
                                                <div className="flex space-x-2">
                                                    <button
                                                        onClick={() => openRestoreModal(user)}
                                                        className="inline-flex items-center px-3 py-1 border border-transparent text-xs font-medium rounded text-indigo-700 bg-indigo-100 hover:bg-indigo-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                                                    >
                                                        Restore
                                                    </button>
                                                    <button
                                                        onClick={() => openDeleteModal(user)}
                                                        className="inline-flex items-center px-3 py-1 border border-transparent text-xs font-medium rounded text-red-700 bg-red-100 hover:bg-red-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500"
                                                    >
                                                        Delete Forever
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </div>
                </div>
            </div>

            {/* Restore Confirmation Modal */}
            {showRestoreModal && userToAction && (
                <div
                    className="fixed inset-0 modal-backdrop-success overflow-y-auto h-full w-full z-50 flex items-center justify-center p-4"
                    onClick={() => {
                        setShowRestoreModal(false);
                        setUserToAction(null);
                    }}
                >
                    <div
                        className="relative w-full max-w-md bg-white rounded-lg shadow-xl"
                        onClick={(e) => e.stopPropagation()}
                    >
                        <div className="p-6">
                            <div className="flex items-center mb-4">
                                <div className="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-green-100">
                                    <svg className="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                    </svg>
                                </div>
                            </div>
                            <h3 className="text-lg font-medium text-gray-900 text-center mb-4">
                                Restore User
                            </h3>
                            <p className="text-sm text-gray-500 text-center mb-6">
                                Are you sure you want to restore <strong>{userToAction.full_name}</strong>? This will make them active again and they will be able to log in.
                            </p>
                            <div className="flex justify-end space-x-3">
                                <button
                                    type="button"
                                    onClick={() => {
                                        setShowRestoreModal(false);
                                        setUserToAction(null);
                                    }}
                                    disabled={restoring}
                                    className="px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50"
                                >
                                    Cancel
                                </button>
                                <button
                                    type="button"
                                    onClick={() => handleRestore(userToAction.id)}
                                    disabled={restoring}
                                    className="px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded-md text-sm font-medium disabled:opacity-50"
                                >
                                    {restoring ? 'Restoring...' : 'Restore'}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            )}

            {/* Permanent Delete Confirmation Modal */}
            {showDeleteModal && userToAction && (
                <div
                    className="fixed inset-0 modal-backdrop-danger overflow-y-auto h-full w-full z-50 flex items-center justify-center p-4"
                    onClick={() => {
                        setShowDeleteModal(false);
                        setUserToAction(null);
                    }}
                >
                    <div
                        className="relative w-full max-w-md bg-white rounded-lg shadow-xl"
                        onClick={(e) => e.stopPropagation()}
                    >
                        <div className="p-6">
                            <div className="flex items-center mb-4">
                                <div className="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-red-100">
                                    <svg className="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z" />
                                    </svg>
                                </div>
                            </div>
                            <h3 className="text-lg font-medium text-gray-900 text-center mb-4">
                                Permanently Delete User
                            </h3>
                            <p className="text-sm text-gray-500 text-center mb-6">
                                Are you sure you want to <strong>permanently delete</strong> <strong>{userToAction.full_name}</strong>? This action cannot be undone and will remove all user data from the database.
                            </p>
                            <div className="flex justify-end space-x-3">
                                <button
                                    type="button"
                                    onClick={() => {
                                        setShowDeleteModal(false);
                                        setUserToAction(null);
                                    }}
                                    disabled={deleting}
                                    className="px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50"
                                >
                                    Cancel
                                </button>
                                <button
                                    type="button"
                                    onClick={() => handleForceDelete(userToAction.id)}
                                    disabled={deleting}
                                    className="px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded-md text-sm font-medium disabled:opacity-50"
                                >
                                    {deleting ? 'Deleting...' : 'Delete Forever'}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            )}

            {/* Alert Modal */}
            <AlertModal
                isOpen={showAlert}
                onClose={() => setShowAlert(false)}
                title={alertType === 'success' ? 'Success' : alertType === 'error' ? 'Error' : 'Information'}
                message={alertMessage}
                type={alertType}
                buttonText="OK"
                autoClose={alertType === 'success'}
                autoCloseDelay={3000}
            />
        </AppLayout>
    );
}
