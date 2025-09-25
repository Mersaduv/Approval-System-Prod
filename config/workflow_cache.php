<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Workflow Cache Configuration
    |--------------------------------------------------------------------------
    |
    | This file contains the cache configuration for the workflow system.
    | You can adjust these settings based on your performance needs.
    |
    */

    'ttl' => [
        'workflow_steps' => env('WORKFLOW_CACHE_TTL_STEPS', 3600), // 1 hour
        'users' => env('WORKFLOW_CACHE_TTL_USERS', 300), // 5 minutes
        'roles' => env('WORKFLOW_CACHE_TTL_ROLES', 3600), // 1 hour
        'departments' => env('WORKFLOW_CACHE_TTL_DEPARTMENTS', 3600), // 1 hour
        'delegations' => env('WORKFLOW_CACHE_TTL_DELEGATIONS', 300), // 5 minutes
        'step_status' => env('WORKFLOW_CACHE_TTL_STEP_STATUS', 300), // 5 minutes
        'approval_rules' => env('WORKFLOW_CACHE_TTL_APPROVAL_RULES', 600), // 10 minutes
        'system_settings' => env('WORKFLOW_CACHE_TTL_SYSTEM_SETTINGS', 3600), // 1 hour
    ],

    'keys' => [
        'workflow_steps' => 'workflow_steps_active',
        'roles' => 'roles_all',
        'departments' => 'departments_all',
        'users_by_role' => 'users_by_role_',
        'users_by_department' => 'users_by_department_',
        'user' => 'user_',
        'employee' => 'employee_',
        'admin_user' => 'admin_user',
        'manager_dept' => 'manager_dept_',
        'delegations' => 'delegations_',
        'step_completed' => 'step_completed_',
        'step_started' => 'step_started_',
        'assignments_completed' => 'assignments_completed_',
        'all_approvals_complete' => 'all_approvals_complete_',
        'approval_rules_dept' => 'approval_rules_dept_',
        'workflow_thresholds' => 'workflow_thresholds',
        'auto_approval_threshold' => 'auto_approval_threshold',
    ],

    'enabled' => env('WORKFLOW_CACHE_ENABLED', true),
];
