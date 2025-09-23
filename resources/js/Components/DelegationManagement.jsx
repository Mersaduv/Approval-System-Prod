import React, { useState, useEffect } from 'react';
import axios from 'axios';
import AlertModal from './AlertModal';
import ConfirmationModal from './ConfirmationModal';
import { CardSkeleton, DelegationItemSkeleton } from './SkeletonLoader';

const DelegationManagement = ({ auth }) => {
    const [delegations, setDelegations] = useState([]);
    const [loading, setLoading] = useState(true);
    const [showCreateModal, setShowCreateModal] = useState(false);
    const [showEditModal, setShowEditModal] = useState(false);
    const [editingDelegation, setEditingDelegation] = useState(null);
    const [availableUsers, setAvailableUsers] = useState([]);
    const [workflowSteps, setWorkflowSteps] = useState([]);
    const [stats, setStats] = useState({});
    const [activeTab, setActiveTab] = useState('all'); // 'my', 'received', 'all'
    const [filter, setFilter] = useState('all'); // 'all', 'active', 'expired', 'inactive'
    const [showUserDropdown, setShowUserDropdown] = useState(false);
    const [usersLoading, setUsersLoading] = useState(false);
    const [showAlert, setShowAlert] = useState(false);
    const [alertMessage, setAlertMessage] = useState('');
    const [alertType, setAlertType] = useState('info');
    const [showConfirmModal, setShowConfirmModal] = useState(false);
    const [confirmAction, setConfirmAction] = useState(null);
    const [confirmData, setConfirmData] = useState(null);
    const [rejectReason, setRejectReason] = useState('');

    const [formData, setFormData] = useState({
        delegate_id: '',
        delegate_search: '',
        workflow_step_id: '',
        reason: '',
        starts_at: '',
        expires_at: '',
        is_active: true,
        permissions: []
    });

    const showAlertMessage = (message, type = 'info') => {
        setAlertMessage(message);
        setAlertType(type);
        setShowAlert(true);
    };

    useEffect(() => {
        loadData();
    }, [activeTab, filter]);

    // Load data on component mount
    useEffect(() => {
        loadData();
    }, []);

    // Close dropdown when clicking outside
    useEffect(() => {
        const handleClickOutside = (event) => {
            if (showUserDropdown && !event.target.closest('.user-dropdown-container')) {
                setShowUserDropdown(false);
            }
        };

        document.addEventListener('mousedown', handleClickOutside);
        return () => {
            document.removeEventListener('mousedown', handleClickOutside);
        };
    }, [showUserDropdown]);

    const loadData = async () => {
        setLoading(true);
        try {
            let endpoint = '/api/delegations';

            // Choose endpoint based on active tab
            if (activeTab === 'my') {
                endpoint = '/api/delegations/my';
            } else if (activeTab === 'received') {
                endpoint = '/api/delegations/received';
            }

            // Add filter parameter
            endpoint += `?status=${filter}`;

            // Load delegations
            const delegationsRes = await axios.get(endpoint);
            setDelegations(delegationsRes.data.data.data || []);

            // Load stats
            const statsRes = await axios.get('/api/delegations/stats');
            setStats(statsRes.data.data || {});

        } catch (error) {
            console.error('Error loading delegation data:', error);
        } finally {
            setLoading(false);
        }
    };

    const loadModalData = async () => {
        setUsersLoading(true);
        try {
            // Load available users
            const usersRes = await axios.get('/api/delegations/available-users');
            if (usersRes.data.success) {
                setAvailableUsers(usersRes.data.data || []);
            } else {
                console.error('Users API returned error:', usersRes.data);
                setAvailableUsers([]);
            }

            // Load workflow steps based on user assignments (all types)
            const stepsRes = await axios.get('/api/delegations/workflow-steps?delegation_type=all');
            if (stepsRes.data.success) {
                setWorkflowSteps(stepsRes.data.data || []);
            } else {
                setWorkflowSteps([]);
            }

        } catch (error) {
            console.error('Error loading modal data:', error);
            setAvailableUsers([]);
            setWorkflowSteps([]);
        } finally {
            setUsersLoading(false);
        }
    };

    const handleCreateDelegation = async (e) => {
        e.preventDefault();
        try {
            const response = await axios.post('/api/delegations', formData);
            if (response.data.success) {
                setShowCreateModal(false);
                resetForm();
                loadData();
                showAlertMessage('Delegation created successfully!', 'success');
            } else {
                showAlertMessage('Error: ' + (response.data.message || 'Failed to create delegation'), 'error');
            }
        } catch (error) {
            console.error('Error creating delegation:', error);
            const errorMessage = error.response?.data?.message || 'Error creating delegation. Please try again.';
            showAlertMessage(errorMessage, 'error');
        }
    };

    const openCreateModal = () => {
        setShowCreateModal(true);
        loadModalData();
    };

    const handleUpdateDelegation = async (e) => {
        e.preventDefault();
        try {
            const response = await axios.put(`/api/delegations/${editingDelegation.id}`, formData);
            if (response.data.success) {
                setShowEditModal(false);
                setEditingDelegation(null);
                resetForm();
                loadData();
                showAlertMessage('Delegation updated successfully!', 'success');
            } else {
                showAlertMessage('Error: ' + (response.data.message || 'Failed to update delegation'), 'error');
            }
        } catch (error) {
            console.error('Error updating delegation:', error);
            const errorMessage = error.response?.data?.message || 'Error updating delegation. Please try again.';
            showAlertMessage(errorMessage, 'error');
        }
    };

    const handleDeleteDelegation = (id) => {
        setConfirmAction('delete');
        setConfirmData({ id });
        setShowConfirmModal(true);
    };

    const handleRejectDelegation = (id) => {
        setConfirmAction('reject');
        setConfirmData({ id });
        setRejectReason(''); // Reset reject reason
        setShowConfirmModal(true);
    };

    const handleConfirmAction = async () => {
        if (confirmAction === 'delete' && confirmData) {
            try {
                const response = await axios.delete(`/api/delegations/${confirmData.id}`);
                if (response.data.success) {
                    loadData();
                } else {
                    showAlertMessage('Error: ' + (response.data.message || 'Failed to delete delegation'), 'error');
                }
            } catch (error) {
                console.error('Error deleting delegation:', error);
                const errorMessage = error.response?.data?.message || 'Error deleting delegation. Please try again.';
                showAlertMessage(errorMessage, 'error');
            }
        } else if (confirmAction === 'reject' && confirmData) {
            try {
                const response = await axios.post(`/api/delegations/${confirmData.id}/reject`, {
                    reason: rejectReason
                });
                if (response.data.success) {
                    loadData();
                } else {
                    showAlertMessage('Error: ' + (response.data.message || 'Failed to reject delegation'), 'error');
                }
            } catch (error) {
                console.error('Error rejecting delegation:', error);
                const errorMessage = error.response?.data?.message || 'Error rejecting delegation. Please try again.';
                showAlertMessage(errorMessage, 'error');
            }
        }
        setShowConfirmModal(false);
        setConfirmAction(null);
        setConfirmData(null);
        setRejectReason('');
    };

    const resetForm = () => {
        setFormData({
            delegate_id: '',
            delegate_search: '',
            workflow_step_id: '',
            reason: '',
            starts_at: '',
            expires_at: '',
            is_active: true,
            permissions: []
        });
        setShowUserDropdown(false);
    };

    const openEditModal = (delegation) => {
        setEditingDelegation(delegation);
        setFormData({
            delegate_id: delegation.delegate_id,
            delegate_search: delegation.delegate ? `${delegation.delegate.full_name} (${delegation.delegate.role?.name})` : '',
            workflow_step_id: delegation.workflow_step_id || '',
            reason: delegation.reason || '',
            starts_at: delegation.starts_at ? delegation.starts_at.split('T')[0] : '',
            expires_at: delegation.expires_at ? delegation.expires_at.split('T')[0] : '',
            is_active: delegation.is_active,
            permissions: delegation.permissions || []
        });
        setShowUserDropdown(false);
        setShowEditModal(true);
        // Load modal data - no need for delegation type
        loadModalData();
    };


    const getStatusBadge = (delegation) => {
        const now = new Date();
        const startsAt = delegation.starts_at ? new Date(delegation.starts_at) : null;
        const expiresAt = delegation.expires_at ? new Date(delegation.expires_at) : null;

        if (!delegation.is_active) {
            return <span className="px-2 py-1 text-xs font-medium bg-gray-100 text-gray-800 rounded-full">Inactive</span>;
        }

        if (startsAt && now < startsAt) {
            return <span className="px-2 py-1 text-xs font-medium bg-yellow-100 text-yellow-800 rounded-full">Scheduled</span>;
        }

        if (expiresAt && now > expiresAt) {
            return <span className="px-2 py-1 text-xs font-medium bg-red-100 text-red-800 rounded-full">Expired</span>;
        }

        return <span className="px-2 py-1 text-xs font-medium bg-green-100 text-green-800 rounded-full">Active</span>;
    };

    const formatDate = (dateString) => {
        if (!dateString) return 'N/A';
        return new Date(dateString).toLocaleDateString();
    };

    // Remove full page loading - we'll show skeleton loading instead

    return (
        <div className="space-y-6">
            {/* Header */}
            <div className="flex justify-between items-center">
                <div>
                    <h2 className="text-2xl font-bold text-gray-900">Delegation Management</h2>
                    <p className="text-gray-600">Manage your approval delegations</p>
                </div>
                <button
                    onClick={openCreateModal}
                    className="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors"
                >
                    <svg className="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                    </svg>
                    Add Delegation
                </button>
            </div>

            {/* Stats */}
            <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
                {loading ? (
                    Array.from({ length: 4 }).map((_, index) => (
                        <div key={index} className="bg-white p-4 rounded-lg shadow animate-pulse">
                            <div className="h-4 bg-gray-200 rounded w-24 mb-2"></div>
                            <div className="h-8 bg-gray-200 rounded w-16"></div>
                        </div>
                    ))
                ) : (
                    <>
                        <div className="bg-white p-4 rounded-lg shadow">
                            <div className="text-sm font-medium text-gray-500">Total Delegations</div>
                            <div className="text-2xl font-bold text-gray-900">{stats.total_delegations || 0}</div>
                        </div>
                        <div className="bg-white p-4 rounded-lg shadow">
                            <div className="text-sm font-medium text-gray-500">Active Delegations</div>
                            <div className="text-2xl font-bold text-green-600">{stats.active_delegations || 0}</div>
                        </div>
                        <div className="bg-white p-4 rounded-lg shadow">
                            <div className="text-sm font-medium text-gray-500">Received Delegations</div>
                            <div className="text-2xl font-bold text-blue-600">{stats.received_delegations || 0}</div>
                        </div>
                        <div className="bg-white p-4 rounded-lg shadow">
                            <div className="text-sm font-medium text-gray-500">Expired</div>
                            <div className="text-2xl font-bold text-red-600">{stats.expired_delegations || 0}</div>
                        </div>
                    </>
                )}
            </div>

            {/* Filter */}
            <div className="flex justify-between items-center">
                <div className="flex space-x-4">
                    <select
                        value={filter}
                        onChange={(e) => setFilter(e.target.value)}
                        className="border border-gray-300 rounded-md px-3 py-2"
                    >
                        <option value="all">All Status</option>
                        <option value="active">Active</option>
                        <option value="expired">Expired</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>
            </div>

            {/* Tabs */}
            <div className="border-b border-gray-200">
                <nav className="-mb-px flex space-x-8">
                    {[
                        { key: 'all', label: 'All Delegations' },
                        { key: 'my', label: 'My Delegations' },
                        { key: 'received', label: 'Received Delegations' }
                    ].map((tab) => (
                        <button
                            key={tab.key}
                            onClick={() => setActiveTab(tab.key)}
                            className={`py-2 px-1 border-b-2 font-medium text-sm ${
                                activeTab === tab.key
                                    ? 'border-blue-500 text-blue-600'
                                    : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
                            }`}
                        >
                            {tab.label}
                        </button>
                    ))}
                </nav>
            </div>

            {/* Delegations List */}
            <div className="bg-white shadow overflow-hidden sm:rounded-md">
                {loading ? (
                    <ul className="divide-y divide-gray-200">
                        <DelegationItemSkeleton count={5} />
                    </ul>
                ) : delegations.length === 0 ? (
                    <div className="text-center py-12">
                        <svg className="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                        <h3 className="mt-2 text-sm font-medium text-gray-900">
                            {activeTab === 'my'
                                ? 'No delegations created'
                                : activeTab === 'received'
                                ? 'No delegations received'
                                : 'No delegations found'
                            }
                        </h3>
                        <p className="mt-1 text-sm text-gray-500">
                            {activeTab === 'my'
                                ? 'You haven\'t created any delegations yet. Click "Add Delegation" to get started.'
                                : activeTab === 'received'
                                ? 'No one has delegated tasks to you yet.'
                                : 'No delegations match your current filter.'
                            }
                        </p>
                    </div>
                ) : (
                    <ul className="divide-y divide-gray-200">
                        {delegations.map((delegation) => (
                        <li key={delegation.id} className="px-6 py-4">
                            <div className="flex items-center justify-between">
                                <div className="flex-1">
                                    <div className="flex items-center space-x-3">
                                        <h3 className="text-sm font-medium text-gray-900">
                                            {activeTab === 'received'
                                                ? delegation.delegator?.full_name
                                                : activeTab === 'my'
                                                ? delegation.delegate?.full_name
                                                : delegation.delegator_id === auth.user.id
                                                ? `To: ${delegation.delegate?.full_name}`
                                                : `From: ${delegation.delegator?.full_name}`
                                            }
                                        </h3>
                                        {getStatusBadge(delegation)}
                                    </div>
                                    <div className="mt-1 text-sm text-gray-500">
                                        <p>
                                            {delegation.workflow_step && (
                                                <span><strong>Step:</strong> {delegation.workflow_step.name}</span>
                                            )}
                                            {delegation.department && (
                                                <span> • <strong>Department:</strong> {delegation.department.name}</span>
                                            )}
                                        </p>
                                        {delegation.reason && (
                                            <p className="mt-1"><strong>Reason:</strong> {delegation.reason}</p>
                                        )}
                                        {delegation.reject_reason && (
                                            <p className="mt-1 text-red-600"><strong>Reject Reason:</strong> {delegation.reject_reason}</p>
                                        )}
                                        <p className="mt-1">
                                            <strong>Period:</strong> {formatDate(delegation.starts_at)} - {formatDate(delegation.expires_at)}
                                        </p>
                                    </div>
                                </div>
                                <div className="flex space-x-2">
                                    {activeTab === 'received' ? (
                                        // For received delegations, show Reject button
                                        <button
                                            onClick={() => handleRejectDelegation(delegation.id)}
                                            disabled={!delegation.is_active}
                                            className={`inline-flex items-center px-3 py-1.5 border border-transparent text-xs font-medium rounded-md text-white focus:outline-none focus:ring-2 focus:ring-offset-2 transition-colors ${
                                                delegation.is_active
                                                    ? 'bg-orange-600 hover:bg-orange-700 focus:ring-orange-500'
                                                    : 'bg-gray-400 cursor-not-allowed'
                                            }`}
                                        >
                                            <svg className="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                                            </svg>
                                            Reject
                                        </button>
                                    ) : activeTab === 'my' ? (
                                        // For my delegations, show appropriate buttons based on status
                                        <>
                                            {delegation.reject_reason ? (
                                                // If rejected, show Rejected status and Delete button
                                                <>
                                                    <button
                                                        disabled
                                                        className="inline-flex items-center px-3 py-1.5 border border-transparent text-xs font-medium rounded-md text-white bg-red-500 cursor-not-allowed"
                                                    >
                                                        <svg className="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                                                        </svg>
                                                        Rejected
                                                    </button>
                                                    <button
                                                        onClick={() => handleDeleteDelegation(delegation.id)}
                                                        className="inline-flex items-center px-3 py-1.5 border border-transparent text-xs font-medium rounded-md text-white bg-red-600 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 transition-colors"
                                                    >
                                                        <svg className="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                        </svg>
                                                        Delete
                                                    </button>
                                                </>
                                            ) : (
                                                // If not rejected, show Edit and Delete buttons
                                                <>
                                                    <button
                                                        onClick={() => openEditModal(delegation)}
                                                        className="inline-flex items-center px-3 py-1.5 border border-transparent text-xs font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors"
                                                    >
                                                        <svg className="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                        </svg>
                                                        Edit
                                                    </button>
                                                    <button
                                                        onClick={() => handleDeleteDelegation(delegation.id)}
                                                        className="inline-flex items-center px-3 py-1.5 border border-transparent text-xs font-medium rounded-md text-white bg-red-600 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 transition-colors"
                                                    >
                                                        <svg className="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                        </svg>
                                                        Delete
                                                    </button>
                                                </>
                                            )}
                                        </>
                                    ) : (
                                        // For all delegations, show appropriate buttons based on user role and status
                                        <>
                                            {delegation.delegator_id === auth.user.id ? (
                                                // User is the delegator
                                                <>
                                                    {delegation.reject_reason ? (
                                                        // If rejected, show Rejected status and Delete button
                                                        <>
                                                            <button
                                                                disabled
                                                                className="inline-flex items-center px-3 py-1.5 border border-transparent text-xs font-medium rounded-md text-white bg-red-500 cursor-not-allowed"
                                                            >
                                                                <svg className="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                                                                </svg>
                                                                Rejected
                                                            </button>
                                                            <button
                                                                onClick={() => handleDeleteDelegation(delegation.id)}
                                                                className="inline-flex items-center px-3 py-1.5 border border-transparent text-xs font-medium rounded-md text-white bg-red-600 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 transition-colors"
                                                            >
                                                                <svg className="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                                </svg>
                                                                Delete
                                                            </button>
                                                        </>
                                                    ) : (
                                                        // If not rejected, show Edit and Delete buttons
                                                        <>
                                                            <button
                                                                onClick={() => openEditModal(delegation)}
                                                                className="inline-flex items-center px-3 py-1.5 border border-transparent text-xs font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors"
                                                            >
                                                                <svg className="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                                </svg>
                                                                Edit
                                                            </button>
                                                            <button
                                                                onClick={() => handleDeleteDelegation(delegation.id)}
                                                                className="inline-flex items-center px-3 py-1.5 border border-transparent text-xs font-medium rounded-md text-white bg-red-600 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 transition-colors"
                                                            >
                                                                <svg className="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                                </svg>
                                                                Delete
                                                            </button>
                                                        </>
                                                    )}
                                                </>
                                            ) : (
                                                // User is the delegate - show Reject button
                                                <button
                                                    onClick={() => handleRejectDelegation(delegation.id)}
                                                    disabled={!delegation.is_active}
                                                    className={`inline-flex items-center px-3 py-1.5 border border-transparent text-xs font-medium rounded-md text-white focus:outline-none focus:ring-2 focus:ring-offset-2 transition-colors ${
                                                        delegation.is_active
                                                            ? 'bg-orange-600 hover:bg-orange-700 focus:ring-orange-500'
                                                            : 'bg-gray-400 cursor-not-allowed'
                                                    }`}
                                                >
                                                    <svg className="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                                                    </svg>
                                                    Reject
                                                </button>
                                            )}
                                        </>
                                    )}
                                </div>
                            </div>
                        </li>
                        ))}
                    </ul>
                )}
            </div>

            {/* Create Modal */}
            {showCreateModal && (
                <div className="fixed inset-0 bg-gray-600 modal-backdrop  overflow-y-auto h-full w-full z-50">
                    <div className="relative top-10 mx-auto p-6 border w-11/12 max-w-4xl shadow-lg rounded-md bg-white">
                        <div className="mt-3">
                            <h3 className="text-lg font-medium text-gray-900 mb-4">Add Delegation</h3>
                            <form onSubmit={handleCreateDelegation} className="space-y-6">
                                <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div>
                                        <label className="block text-sm font-medium text-gray-700">Delegate To <span className="text-red-500">*</span></label>
                                        <div className="relative user-dropdown-container">
                                            <input
                                                type="text"
                                                value={formData.delegate_search || ''}
                                                onChange={(e) => {
                                                    const searchTerm = e.target.value;
                                                    setFormData({ ...formData, delegate_search: searchTerm, delegate_id: '' });
                                                    setShowUserDropdown(searchTerm.length > 0);
                                                }}
                                                onFocus={() => setShowUserDropdown(true)}
                                                className="mt-1 block w-full border border-gray-300 rounded-md px-3 py-2 pr-10 focus:ring-blue-500 focus:border-blue-500"
                                                placeholder="Search for user..."
                                                required
                                            />
                                            <div className="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none">
                                                <svg className="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                                </svg>
                                            </div>

                                            {showUserDropdown && (
                                                <div className="absolute z-10 mt-1 w-full bg-white shadow-lg max-h-60 rounded-md py-1 text-base ring-1 ring-black ring-opacity-5 overflow-auto focus:outline-none">
                                                    {availableUsers
                                                        .filter(user =>
                                                            user.full_name.toLowerCase().includes(formData.delegate_search?.toLowerCase() || '') ||
                                                            user.role?.name?.toLowerCase().includes(formData.delegate_search?.toLowerCase() || '')
                                                        )
                                                        .map((user) => (
                                                            <div
                                                                key={user.id}
                                                                className="cursor-pointer select-none relative py-2 pl-3 pr-9 hover:bg-blue-50"
                                                                onClick={() => {
                                                                    setFormData({
                                                                        ...formData,
                                                                        delegate_id: user.id,
                                                                        delegate_search: `${user.full_name} (${user.role?.name})`
                                                                    });
                                                                    setShowUserDropdown(false);
                                                                }}
                                                            >
                                                                <div className="flex items-center">
                                                                    <div className="flex-shrink-0 h-8 w-8 rounded-full bg-blue-100 flex items-center justify-center">
                                                                        <span className="text-sm font-medium text-blue-600">
                                                                            {user.full_name.charAt(0).toUpperCase()}
                                                                        </span>
                                                                    </div>
                                                                    <div className="ml-3 flex flex-col">
                                                                        <span className="font-medium text-gray-900">{user.full_name}</span>
                                                                        <span className="text-sm text-gray-500">{user.role?.name}</span>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        ))
                                                    }
                                                    {usersLoading ? (
                                                        <div className="cursor-default select-none relative py-2 pl-3 pr-9 text-gray-500">
                                                            Loading users...
                                                        </div>
                                                    ) : availableUsers.length === 0 ? (
                                                        <div className="cursor-default select-none relative py-2 pl-3 pr-9 text-gray-500">
                                                            No users available
                                                        </div>
                                                    ) : availableUsers.filter(user =>
                                                        user.full_name.toLowerCase().includes(formData.delegate_search?.toLowerCase() || '') ||
                                                        user.role?.name?.toLowerCase().includes(formData.delegate_search?.toLowerCase() || '')
                                                    ).length === 0 && (
                                                        <div className="cursor-default select-none relative py-2 pl-3 pr-9 text-gray-500">
                                                            No users found
                                                        </div>
                                                    )}
                                                </div>
                                            )}
                                        </div>
                                    </div>


                                    <div>
                                        <label className="block text-sm font-medium text-gray-700">Workflow Step <span className="text-red-500">*</span></label>
                                        <select
                                            value={formData.workflow_step_id}
                                            onChange={(e) => setFormData({ ...formData, workflow_step_id: e.target.value })}
                                            className="mt-1 block w-full border border-gray-300 rounded-md px-3 py-2"
                                            required
                                        >
                                            <option value="">Select Workflow Step</option>
                                            {workflowSteps.map((step) => (
                                                <option key={step.id} value={step.id}>
                                                    {step.name}
                                                </option>
                                            ))}
                                        </select>
                                    </div>

                                </div>

                                <div>
                                    <label className="block text-sm font-medium text-gray-700">Reason</label>
                                    <textarea
                                        value={formData.reason}
                                        onChange={(e) => setFormData({ ...formData, reason: e.target.value })}
                                        className="mt-1 block w-full border border-gray-300 rounded-md px-3 py-2"
                                        rows={4}
                                        placeholder="Enter reason for delegation..."
                                    />
                                </div>

                                <div className="grid grid-cols-2 gap-4">
                                    <div>
                                        <label className="block text-sm font-medium text-gray-700">Start Date</label>
                                        <input
                                            type="date"
                                            value={formData.starts_at}
                                            onChange={(e) => setFormData({ ...formData, starts_at: e.target.value })}
                                            className="mt-1 block w-full border border-gray-300 rounded-md px-3 py-2"
                                        />
                                    </div>
                                    <div>
                                        <label className="block text-sm font-medium text-gray-700">End Date</label>
                                        <input
                                            type="date"
                                            value={formData.expires_at}
                                            onChange={(e) => setFormData({ ...formData, expires_at: e.target.value })}
                                            className="mt-1 block w-full border border-gray-300 rounded-md px-3 py-2"
                                        />
                                    </div>
                                </div>


                                <div className="flex justify-end space-x-3 pt-4 border-t border-gray-200">
                                    <button
                                        type="button"
                                        onClick={() => setShowCreateModal(false)}
                                        className="px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors"
                                    >
                                        Cancel
                                    </button>
                                    <button
                                        type="submit"
                                        className="px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors"
                                    >
                                        Add Delegation
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            )}

            {/* Edit Modal */}
            {showEditModal && (
                <div className="fixed inset-0 bg-gray-600 modal-backdrop  overflow-y-auto h-full w-full z-50">
                    <div className="relative top-10 mx-auto p-6 border w-11/12 max-w-4xl shadow-lg rounded-md bg-white">
                        <div className="mt-3">
                            <h3 className="text-lg font-medium text-gray-900 mb-4">Edit Delegation</h3>
                            <form onSubmit={handleUpdateDelegation} className="space-y-6">
                                <div>
                                    <label className="block text-sm font-medium text-gray-700">Reason</label>
                                    <textarea
                                        value={formData.reason}
                                        onChange={(e) => setFormData({ ...formData, reason: e.target.value })}
                                        className="mt-1 block w-full border border-gray-300 rounded-md px-3 py-2"
                                        rows={4}
                                        placeholder="Enter reason for delegation..."
                                    />
                                </div>

                                <div className="grid grid-cols-2 gap-4">
                                    <div>
                                        <label className="block text-sm font-medium text-gray-700">Start Date</label>
                                        <input
                                            type="date"
                                            value={formData.starts_at}
                                            onChange={(e) => setFormData({ ...formData, starts_at: e.target.value })}
                                            className="mt-1 block w-full border border-gray-300 rounded-md px-3 py-2"
                                        />
                                    </div>
                                    <div>
                                        <label className="block text-sm font-medium text-gray-700">End Date</label>
                                        <input
                                            type="date"
                                            value={formData.expires_at}
                                            onChange={(e) => setFormData({ ...formData, expires_at: e.target.value })}
                                            className="mt-1 block w-full border border-gray-300 rounded-md px-3 py-2"
                                        />
                                    </div>
                                </div>


                                <div className="flex items-center">
                                    <input
                                        type="checkbox"
                                        id="is_active"
                                        checked={formData.is_active}
                                        onChange={(e) => setFormData({ ...formData, is_active: e.target.checked })}
                                        className="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
                                    />
                                    <label htmlFor="is_active" className="ml-2 block text-sm text-gray-900">
                                        Active
                                    </label>
                                </div>

                                <div className="flex justify-end space-x-3 pt-4 border-t border-gray-200">
                                    <button
                                        type="button"
                                        onClick={() => setShowEditModal(false)}
                                        className="px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors"
                                    >
                                        Cancel
                                    </button>
                                    <button
                                        type="submit"
                                        className="px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors"
                                    >
                                        Update Delegation
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            )}

            {/* Alert Modal */}
            <AlertModal
                isOpen={showAlert}
                onClose={() => setShowAlert(false)}
                title={
                    alertType === "success"
                        ? "Success"
                        : alertType === "error"
                        ? "Error"
                        : alertType === "warning"
                        ? "Warning"
                        : "Information"
                }
                message={alertMessage}
                type={alertType}
                buttonText="OK"
                autoClose={alertType === "success"}
                autoCloseDelay={3000}
            />

            {/* Confirmation Modal */}
            <ConfirmationModal
                isOpen={showConfirmModal}
                onClose={() => {
                    setShowConfirmModal(false);
                    setConfirmAction(null);
                    setConfirmData(null);
                    setRejectReason('');
                }}
                onConfirm={handleConfirmAction}
                title="Confirm Action"
                message={
                    confirmAction === 'delete'
                        ? 'Are you sure you want to delete this delegation? This action cannot be undone.'
                        : confirmAction === 'reject'
                        ? 'Are you sure you want to reject this delegation? The delegator will be notified that you declined.'
                        : 'Are you sure you want to proceed?'
                }
                confirmText={
                    confirmAction === 'delete'
                        ? 'Delete'
                        : confirmAction === 'reject'
                        ? 'Reject'
                        : 'Confirm'
                }
                cancelText="Cancel"
                type="danger"
                showRejectReason={confirmAction === 'reject'}
                rejectReason={rejectReason}
                onRejectReasonChange={setRejectReason}
            />
        </div>
    );
};

export default DelegationManagement;
