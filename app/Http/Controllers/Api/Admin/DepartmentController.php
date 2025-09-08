<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class DepartmentController extends Controller
{
    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            if (Auth::user()->role !== 'Admin') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized. Admin access required.'
                ], 403);
            }
            return $next($request);
        });
    }

    /**
     * Display a listing of departments
     */
    public function index(Request $request): JsonResponse
    {
        $query = Department::withCount(['users', 'approvalRules']);

        // Search by name
        if ($request->has('search')) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        $departments = $query->orderBy('name')->get();

        return response()->json([
            'success' => true,
            'data' => $departments
        ]);
    }

    /**
     * Store a newly created department
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:departments,name',
            'description' => 'nullable|string|max:1000'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $department = Department::create($request->all());

            return response()->json([
                'success' => true,
                'message' => 'Department created successfully',
                'data' => $department
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create department',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified department
     */
    public function show(string $id): JsonResponse
    {
        $department = Department::withCount(['users', 'approvalRules'])
            ->with(['users' => function($query) {
                $query->select('id', 'full_name', 'email', 'role', 'department_id');
            }])
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $department
        ]);
    }

    /**
     * Update the specified department
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $department = Department::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:departments,name,' . $id,
            'description' => 'nullable|string|max:1000'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $department->update($request->all());

            return response()->json([
                'success' => true,
                'message' => 'Department updated successfully',
                'data' => $department
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update department',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified department
     */
    public function destroy(string $id): JsonResponse
    {
        try {
            $department = Department::findOrFail($id);

            // Check if department has users
            if ($department->users()->count() > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete department with existing users. Please reassign users first.'
                ], 422);
            }

            // Check if department has approval rules
            if ($department->approvalRules()->count() > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete department with existing approval rules. Please delete rules first.'
                ], 422);
            }

            $department->delete();

            return response()->json([
                'success' => true,
                'message' => 'Department deleted successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete department',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get department statistics
     */
    public function getStats(): JsonResponse
    {
        $stats = [
            'total_departments' => Department::count(),
            'total_users' => User::count(),
            'users_by_department' => Department::withCount('users')
                ->orderBy('users_count', 'desc')
                ->get()
                ->map(function($dept) {
                    return [
                        'department_name' => $dept->name,
                        'user_count' => $dept->users_count
                    ];
                }),
            'roles_distribution' => User::selectRaw('role, COUNT(*) as count')
                ->groupBy('role')
                ->get()
                ->mapWithKeys(function($item) {
                    return [$item->role => $item->count];
                })
        ];

        return response()->json([
            'success' => true,
            'data' => $stats
        ]);
    }
}
