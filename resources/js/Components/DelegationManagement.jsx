import React, { useState, useEffect } from 'react';
import axios from 'axios';

const DelegationManagement = ({ auth }) => {
    const [delegations, setDelegations] = useState([]);
    const [loading, setLoading] = useState(true);
    const [showCreateModal, setShowCreateModal] = useState(false);
    const [showEditModal, setShowEditModal] = useState(false);
    const [editingDelegation, setEditingDelegation] = useState(null);
    const [availableUsers, setAvailableUsers] = useState([]);
    const [workflowSteps, setWorkflowSteps] = useState([]);
    const [stats, setStats] = useState({});
    const [activeTab, setActiveTab] = useState('my'); // 'my', 'received', 'all'
    const [filter, setFilter] = useState('all'); // 'all', 'active', 'expired', 'inactive'
    const [showUserDropdown, setShowUserDropdown] = useState(false);
    const [usersLoading, setUsersLoading] = useState(false);

    const [formData, setFormData] = useState({
        delegate_id: '',
        delegate_search: '',
        workflow_step_id: '',
        delegation_type: 'approval',
        reason: '',
        starts_at: '',
        expires_at: '',
        can_delegate_further: false,
        permissions: []
    });

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
            // Load delegations
            const delegationsRes = await axios.get(`/api/delegations?status=${filter}`);
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

    const loadModalData = async (delegationType = 'approval') => {
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

            // Load workflow steps based on delegation type
            const stepsRes = await axios.get(`/api/delegations/workflow-steps?delegation_type=${delegationType}`);
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
            await axios.post('/api/delegations', formData);
            setShowCreateModal(false);
            resetForm();
            loadData();
        } catch (error) {
            console.error('Error creating delegation:', error);
        }
    };

    const openCreateModal = () => {
        setShowCreateModal(true);
        loadModalData('approval'); // Default to approval type
    };

    const handleUpdateDelegation = async (e) => {
        e.preventDefault();
        try {
            await axios.put(`/api/delegations/${editingDelegation.id}`, formData);
            setShowEditModal(false);
            setEditingDelegation(null);
            resetForm();
            loadData();
        } catch (error) {
            console.error('Error updating delegation:', error);
        }
    };

    const handleDeleteDelegation = async (id) => {
        if (window.confirm('Are you sure you want to delete this delegation?')) {
            try {
                await axios.delete(`/api/delegations/${id}`);
                loadData();
            } catch (error) {
                console.error('Error deleting delegation:', error);
            }
        }
    };

    const resetForm = () => {
        setFormData({
            delegate_id: '',
            delegate_search: '',
            workflow_step_id: '',
            delegation_type: 'approval',
            reason: '',
            starts_at: '',
            expires_at: '',
            can_delegate_further: false,
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
            delegation_type: delegation.delegation_type,
            reason: delegation.reason || '',
            starts_at: delegation.starts_at ? delegation.starts_at.split('T')[0] : '',
            expires_at: delegation.expires_at ? delegation.expires_at.split('T')[0] : '',
            can_delegate_further: delegation.can_delegate_further,
            permissions: delegation.permissions || []
        });
        setShowUserDropdown(false);
        setShowEditModal(true);
        loadModalData(delegation.delegation_type);
    };

    const handleDelegationTypeChange = async (newType) => {
        setFormData({ ...formData, delegation_type: newType, workflow_step_id: '' });
        await loadModalData(newType);
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

    if (loading) {
        return (
            <div className="flex justify-center items-center h-64">
                <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600"></div>
            </div>
        );
    }

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
                    className="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition-colors"
                >
                    Add Delegation
                </button>
            </div>

            {/* Stats */}
            <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
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
                        { key: 'my', label: 'My Delegations' },
                        { key: 'received', label: 'Received Delegations' },
                        { key: 'all', label: 'All Delegations' }
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
                <ul className="divide-y divide-gray-200">
                    {delegations.map((delegation) => (
                        <li key={delegation.id} className="px-6 py-4">
                            <div className="flex items-center justify-between">
                                <div className="flex-1">
                                    <div className="flex items-center space-x-3">
                                        <h3 className="text-sm font-medium text-gray-900">
                                            {activeTab === 'received' ? delegation.delegator?.full_name : delegation.delegate?.full_name}
                                        </h3>
                                        {getStatusBadge(delegation)}
                                    </div>
                                    <div className="mt-1 text-sm text-gray-500">
                                        <p>
                                            <strong>Type:</strong> {delegation.delegation_type}
                                            {delegation.workflow_step && (
                                                <span> • <strong>Step:</strong> {delegation.workflow_step.name}</span>
                                            )}
                                            {delegation.department && (
                                                <span> • <strong>Department:</strong> {delegation.department.name}</span>
                                            )}
                                        </p>
                                        {delegation.reason && (
                                            <p className="mt-1"><strong>Reason:</strong> {delegation.reason}</p>
                                        )}
                                        <p className="mt-1">
                                            <strong>Period:</strong> {formatDate(delegation.starts_at)} - {formatDate(delegation.expires_at)}
                                        </p>
                                    </div>
                                </div>
                                <div className="flex space-x-2">
                                    <button
                                        onClick={() => openEditModal(delegation)}
                                        className="text-blue-600 hover:text-blue-900 text-sm font-medium"
                                    >
                                        Edit
                                    </button>
                                    <button
                                        onClick={() => handleDeleteDelegation(delegation.id)}
                                        className="text-red-600 hover:text-red-900 text-sm font-medium"
                                    >
                                        Delete
                                    </button>
                                </div>
                            </div>
                        </li>
                    ))}
                </ul>
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
                                        <label className="block text-sm font-medium text-gray-700">Delegation Type</label>
                                        <select
                                            value={formData.delegation_type}
                                            onChange={(e) => handleDelegationTypeChange(e.target.value)}
                                            className="mt-1 block w-full border border-gray-300 rounded-md px-3 py-2"
                                        >
                                            <option value="approval">Approval</option>
                                            <option value="verification">Verification</option>
                                            <option value="notification">Notification</option>
                                            <option value="all">All</option>
                                        </select>
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
                                                    {step.name} ({step.step_type})
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

                                <div className="flex items-center">
                                    <input
                                        type="checkbox"
                                        id="can_delegate_further"
                                        checked={formData.can_delegate_further}
                                        onChange={(e) => setFormData({ ...formData, can_delegate_further: e.target.checked })}
                                        className="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
                                    />
                                    <label htmlFor="can_delegate_further" className="ml-2 block text-sm text-gray-900">
                                        Allow further delegation
                                    </label>
                                </div>

                                <div className="flex justify-end space-x-4 pt-4 border-t border-gray-200">
                                    <button
                                        type="button"
                                        onClick={() => setShowCreateModal(false)}
                                        className="px-6 py-3 text-base font-medium text-gray-700 bg-gray-100 rounded-md hover:bg-gray-200 transition-colors"
                                    >
                                        Cancel
                                    </button>
                                    <button
                                        type="submit"
                                        className="px-6 py-3 text-base font-medium text-white bg-blue-600 rounded-md hover:bg-blue-700 transition-colors"
                                    >
                                        Create Delegation
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
                                        id="edit_can_delegate_further"
                                        checked={formData.can_delegate_further}
                                        onChange={(e) => setFormData({ ...formData, can_delegate_further: e.target.checked })}
                                        className="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
                                    />
                                    <label htmlFor="edit_can_delegate_further" className="ml-2 block text-sm text-gray-900">
                                        Allow further delegation
                                    </label>
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

                                <div className="flex justify-end space-x-4 pt-4 border-t border-gray-200">
                                    <button
                                        type="button"
                                        onClick={() => setShowEditModal(false)}
                                        className="px-6 py-3 text-base font-medium text-gray-700 bg-gray-100 rounded-md hover:bg-gray-200 transition-colors"
                                    >
                                        Cancel
                                    </button>
                                    <button
                                        type="submit"
                                        className="px-6 py-3 text-base font-medium text-white bg-blue-600 rounded-md hover:bg-blue-700 transition-colors"
                                    >
                                        Update Delegation
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
};

export default DelegationManagement;
