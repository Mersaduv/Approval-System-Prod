<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use App\Models\WorkflowStep;
use App\Models\User;
use App\Models\Role;
use App\Models\Department;

class CacheService
{
    const CACHE_TTL = 3600; // 1 hour (fallback)
    const WORKFLOW_STEPS_CACHE_KEY = 'workflow_steps_active';
    const ROLES_CACHE_KEY = 'roles_all';
    const DEPARTMENTS_CACHE_KEY = 'departments_all';
    const USERS_BY_ROLE_CACHE_KEY = 'users_by_role_';
    const USERS_BY_DEPARTMENT_CACHE_KEY = 'users_by_department_';

    /**
     * Get cache TTL from config
     */
    private static function getTtl(string $key): int
    {
        return config("workflow_cache.ttl.{$key}", self::CACHE_TTL);
    }

    /**
     * Get cache key from config
     */
    private static function getKey(string $key): string
    {
        return config("workflow_cache.keys.{$key}", $key);
    }

    /**
     * Check if caching is enabled
     */
    private static function isEnabled(): bool
    {
        return config('workflow_cache.enabled', true);
    }

    /**
     * Get active workflow steps with caching
     */
    public static function getActiveWorkflowSteps($category = null)
    {
        if (!self::isEnabled()) {
            return WorkflowStep::getActiveSteps($category);
        }

        $cacheKey = $category ? self::getKey('workflow_steps') . "_{$category}" : self::getKey('workflow_steps');

        return Cache::remember(
            $cacheKey,
            self::getTtl('workflow_steps'),
            function () use ($category) {
                return WorkflowStep::getActiveSteps($category);
            }
        );
    }

    /**
     * Get all roles with caching
     */
    public static function getAllRoles()
    {
        return Cache::remember(self::ROLES_CACHE_KEY, self::CACHE_TTL, function () {
            return Role::all();
        });
    }

    /**
     * Get all departments with caching
     */
    public static function getAllDepartments()
    {
        return Cache::remember(self::DEPARTMENTS_CACHE_KEY, self::CACHE_TTL, function () {
            return Department::all();
        });
    }

    /**
     * Get users by role with caching
     */
    public static function getUsersByRole($roleName)
    {
        $cacheKey = self::USERS_BY_ROLE_CACHE_KEY . $roleName;

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($roleName) {
            return User::whereHas('role', function($query) use ($roleName) {
                $query->where('name', $roleName);
            })->get();
        });
    }

    /**
     * Get users by department with caching
     */
    public static function getUsersByDepartment($departmentId)
    {
        $cacheKey = self::USERS_BY_DEPARTMENT_CACHE_KEY . $departmentId;

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($departmentId) {
            return User::where('department_id', $departmentId)->get();
        });
    }

    /**
     * Clear all workflow-related caches
     */
    public static function clearWorkflowCaches()
    {
        Cache::forget(self::WORKFLOW_STEPS_CACHE_KEY);
        Cache::forget(self::ROLES_CACHE_KEY);
        Cache::forget(self::DEPARTMENTS_CACHE_KEY);

        // Clear user caches
        $roles = ['admin', 'manager', 'procurement', 'employee'];
        foreach ($roles as $role) {
            Cache::forget(self::USERS_BY_ROLE_CACHE_KEY . $role);
        }

        // Clear department caches
        $departments = Department::pluck('id');
        foreach ($departments as $deptId) {
            Cache::forget(self::USERS_BY_DEPARTMENT_CACHE_KEY . $deptId);
        }
    }

    /**
     * Clear specific user caches
     */
    public static function clearUserCaches($userId = null)
    {
        if ($userId) {
            $user = User::find($userId);
            if ($user) {
                Cache::forget(self::USERS_BY_ROLE_CACHE_KEY . $user->role->name);
                Cache::forget(self::USERS_BY_DEPARTMENT_CACHE_KEY . $user->department_id);
            }
        } else {
            // Clear all user caches
            $roles = ['admin', 'manager', 'procurement', 'employee'];
            foreach ($roles as $role) {
                Cache::forget(self::USERS_BY_ROLE_CACHE_KEY . $role);
            }

            $departments = Department::pluck('id');
            foreach ($departments as $deptId) {
                Cache::forget(self::USERS_BY_DEPARTMENT_CACHE_KEY . $deptId);
            }
        }
    }
}
