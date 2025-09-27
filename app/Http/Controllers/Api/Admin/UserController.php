<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Department;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class UserController extends Controller
{
    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            if (Auth::user()->role->name !== 'admin') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized. Admin access required.'
                ], 403);
            }
            return $next($request);
        });
    }

    /**
     * Display a listing of users
     */
    public function index(Request $request): JsonResponse
    {
        $query = User::with(['department', 'role']);

        // Filter by role
        if ($request->has('role_id')) {
            $query->where('role_id', $request->role_id);
        }

        // Filter by department
        if ($request->has('department_id')) {
            $query->where('department_id', $request->department_id);
        }

        // Search by name or email
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('full_name', 'like', '%' . $search . '%')
                  ->orWhere('email', 'like', '%' . $search . '%');
            });
        }

        $users = $query->orderBy('full_name')->paginate(15);

        return response()->json([
            'success' => true,
            'data' => $users
        ]);
    }

    /**
     * Store a newly created user
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'full_name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8',
            'department_id' => 'required|exists:departments,id',
            'role_id' => 'required|exists:roles,id',
            'permissions' => 'nullable|array'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $userData = $request->all();
            $userData['password'] = Hash::make($request->password);

            if ($request->has('permissions')) {
                $userData['permissions'] = $request->permissions;
            }

            $user = User::create($userData);

            return response()->json([
                'success' => true,
                'message' => 'User created successfully',
                'data' => $user->load(['department', 'role'])
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create user',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified user
     */
    public function show(string $id): JsonResponse
    {
        $user = User::with(['department', 'role', 'requests', 'auditLogs'])
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $user
        ]);
    }

    /**
     * Update the specified user
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $user = User::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'full_name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,' . $id,
            'password' => 'nullable|string|min:8',
            'department_id' => 'required|exists:departments,id',
            'role_id' => 'required|exists:roles,id',
            'permissions' => 'nullable|array'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $userData = $request->except('password');

            if ($request->has('password')) {
                $userData['password'] = Hash::make($request->password);
            }

            if ($request->has('permissions')) {
                $userData['permissions'] = $request->permissions;
            }

            $user->update($userData);

            return response()->json([
                'success' => true,
                'message' => 'User updated successfully',
                'data' => $user->load(['department', 'role'])
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update user',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified user
     */
    public function destroy(string $id): JsonResponse
    {
        try {
            $user = User::findOrFail($id);

            // Prevent admin from deleting themselves
            if ($user->id === Auth::id()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete your own account'
                ], 422);
            }

            // Check if user has pending requests
            if ($user->requests()->where('status', 'Pending')->count() > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete user with pending requests'
                ], 422);
            }

            // Soft delete the user
            $user->delete();

            // Log the action
            \App\Models\AuditLog::create([
                'user_id' => Auth::id(),
                'request_id' => null,
                'action' => 'User Moved to Trash',
                'notes' => "User '{$user->full_name}' moved to trash",
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'User moved to trash successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete user',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get available roles (fixed roles only)
     */
    public function getRoles(): JsonResponse
    {
        $roles = Role::whereIn('name', [Role::ADMIN, Role::MANAGER, Role::EMPLOYEE])
            ->where('is_active', true)
            ->select('id', 'name', 'description')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $roles
        ]);
    }

    /**
     * Get trashed users
     */
    public function trash(): JsonResponse
    {
        try {
            $users = User::onlyTrashed()
                ->with(['role', 'department'])
                ->orderBy('deleted_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $users
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch trashed users',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Restore a trashed user
     */
    public function restore(string $id): JsonResponse
    {
        try {
            $user = User::onlyTrashed()->findOrFail($id);

            $user->restore();

            // Log the action (only if user is authenticated)
            if (Auth::check()) {
                \App\Models\AuditLog::create([
                    'user_id' => Auth::id(),
                    'request_id' => null,
                    'action' => 'User Restored from Trash',
                    'notes' => "User '{$user->full_name}' restored from trash",
                    'ip_address' => request()->ip(),
                    'user_agent' => request()->userAgent()
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'User restored successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to restore user',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Permanently delete a user
     */
    public function forceDelete(string $id): JsonResponse
    {
        try {
            $user = User::onlyTrashed()->findOrFail($id);

            // Log the action before permanent deletion (only if user is authenticated)
            if (Auth::check()) {
                \App\Models\AuditLog::create([
                    'user_id' => Auth::id(),
                    'request_id' => null,
                    'action' => 'User Permanently Deleted',
                    'notes' => "User '{$user->full_name}' permanently deleted from trash",
                    'ip_address' => request()->ip(),
                    'user_agent' => request()->userAgent()
                ]);
            }

            $user->forceDelete();

            return response()->json([
                'success' => true,
                'message' => 'User permanently deleted'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to permanently delete user',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get available permissions
     */
    public function getPermissions(): JsonResponse
    {
        $permissions = [
            'submit_requests' => 'Submit Requests',
            'approve_requests' => 'Approve Requests',
            'approve_purchases' => 'Approve Purchases',
            'approve_high_value' => 'Approve High Value Requests',
            'manage_procurement' => 'Manage Procurement',
            'manage_team' => 'Manage Team',
            'manage_sales' => 'Manage Sales',
            'view_all_requests' => 'View All Requests',
            'manage_users' => 'Manage Users',
            'manage_departments' => 'Manage Departments',
            'manage_approval_rules' => 'Manage Approval Rules',
            'view_audit_logs' => 'View Audit Logs',
            'view_reports' => 'View Reports',
            '*' => 'All Permissions (Admin)'
        ];

        return response()->json([
            'success' => true,
            'data' => $permissions
        ]);
    }

    /**
     * Update user permissions
     */
    public function updatePermissions(Request $request, string $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'permissions' => 'required|array'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $user = User::findOrFail($id);
            $user->update(['permissions' => json_encode($request->permissions)]);

            return response()->json([
                'success' => true,
                'message' => 'User permissions updated successfully',
                'data' => $user->load('department')
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update permissions',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get user statistics
     */
    public function getStats(): JsonResponse
    {
        $stats = [
            'total_users' => User::count(),
            'users_by_role' => User::with('role')
                ->selectRaw('role_id, COUNT(*) as count')
                ->groupBy('role_id')
                ->get()
                ->mapWithKeys(function($item) {
                    return [$item->role->name => $item->count];
                }),
            'users_by_department' => User::with('department')
                ->selectRaw('department_id, COUNT(*) as count')
                ->groupBy('department_id')
                ->get()
                ->map(function($item) {
                    return [
                        'department_name' => $item->department->name,
                        'user_count' => $item->count
                    ];
                }),
            'recent_users' => User::with('department')
                ->orderBy('created_at', 'desc')
                ->limit(5)
                ->get()
        ];

        return response()->json([
            'success' => true,
            'data' => $stats
        ]);
    }
}
