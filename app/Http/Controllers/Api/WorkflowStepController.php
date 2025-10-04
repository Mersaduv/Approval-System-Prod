<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\WorkflowStep;
use App\Models\WorkflowStepAssignment;
use App\Models\User;
use App\Models\Role;
use App\Models\Department;
use App\Models\FinanceAssignment;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class WorkflowStepController extends Controller
{
    /**
     * Display a listing of workflow steps
     */
    public function index(Request $request): JsonResponse
    {
        $query = WorkflowStep::with(['assignments.assignable']);

        // Filter by category if provided
        if ($request->has('category')) {
            $category = $request->get('category');
            if ($category === 'regular') {
                $query->where(function($q) {
                    $q->whereNull('step_category')
                      ->orWhere('step_category', '')
                      ->orWhere('step_category', 'regular');
                });
            } elseif ($category === 'leave') {
                $query->where('step_category', 'leave');
            }
        }

        $steps = $query->orderBy('order_index')->get();

        // Convert steps to array with properly mapped assignments
        $stepsArray = $steps->map(function ($step) {
            $assignmentsArray = $step->assignments->map(function ($assignment) {
                $assignmentType = $this->getAssignmentTypeFromAssignable($assignment);
                $userId = in_array($assignmentType, ['user', 'finance', 'admin', 'hr']) ? $this->getUserIdFromAssignment($assignment) : null;
                $assignableName = $this->getAssignableName($assignment);

                return [
                    'id' => $assignment->id,
                    'workflow_step_id' => $assignment->workflow_step_id,
                    'assignable_type' => $assignment->assignable_type,
                    'assignable_id' => $assignment->assignable_id,
                    'assignment_type' => $assignmentType,
                    'user_id' => $userId,
                    'is_required' => $assignment->is_required,
                    'conditions' => $assignment->conditions,
                    'created_at' => $assignment->created_at,
                    'updated_at' => $assignment->updated_at,
                    'assignable' => $assignment->assignable,
                    'assignable_name' => $assignableName
                ];
            });

            return [
                'id' => $step->id,
                'name' => $step->name,
                'description' => $step->description,
                'order_index' => $step->order_index,
                'is_active' => $step->is_active,
                'is_required' => $step->is_required,
                'step_type' => $step->step_type,
                'step_category' => $step->step_category,
                'timeout_hours' => $step->timeout_hours,
                'auto_approve' => $step->auto_approve,
                'conditions' => $step->conditions,
                'created_at' => $step->created_at,
                'updated_at' => $step->updated_at,
                'assignments' => $assignmentsArray->toArray()
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $stepsArray->toArray()
        ]);
    }

    /**
     * Store a newly created workflow step
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'order_index' => 'required|integer|min:0',
            'is_active' => 'boolean',
            'step_type' => 'required|in:approval,verification,notification',
            'step_category' => 'nullable|string|in:regular,leave',
            'timeout_hours' => 'nullable|integer|min:1',
            'auto_approve' => 'boolean',
            'conditions' => 'nullable|array',
            'assignments' => 'array',
            'assignments.*.assignment_type' => 'required|string|in:admin,manager,finance,procurement,user,hr',
            'assignments.*.user_id' => 'required_if:assignments.*.assignment_type,user,finance,hr|nullable|integer|exists:users,id',
            'assignments.*.is_required' => 'boolean'
        ]);

        // Custom validation for assignments when auto_approve is false
        $validator->after(function ($validator) use ($request) {
            if (!$request->auto_approve && (!$request->assignments || count($request->assignments) === 0)) {
                $validator->errors()->add('assignments', 'At least one assignment is required when auto approve is disabled.');
            }
        });

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            $stepData = $request->only([
                'name', 'description', 'order_index', 'is_active',
                'step_type', 'step_category', 'timeout_hours', 'auto_approve', 'conditions'
            ]);
            $stepData['is_required'] = true; // All steps are required by default

            $step = WorkflowStep::create($stepData);

            if ($request->has('assignments') && !$request->auto_approve) {
                foreach ($request->assignments as $assignmentData) {
                    $assignableType = $this->getAssignableTypeWithContext($assignmentData);
                    $assignableId = $this->getAssignableId($assignmentData, $assignableType);

                    WorkflowStepAssignment::create([
                        'workflow_step_id' => $step->id,
                        'assignable_type' => $assignableType,
                        'assignable_id' => $assignableId,
                        'is_required' => $assignmentData['is_required'] ?? true,
                        'conditions' => $assignmentData['conditions'] ?? null
                    ]);
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Workflow step created successfully',
                'data' => $step->load('assignments.assignable')
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to create workflow step',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the specified workflow step
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $step = WorkflowStep::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'order_index' => 'sometimes|required|integer|min:0',
            'is_active' => 'boolean',
            'step_type' => 'sometimes|required|in:approval,verification,notification',
            'step_category' => 'nullable|string|in:regular,leave',
            'timeout_hours' => 'nullable|integer|min:1',
            'auto_approve' => 'boolean',
            'conditions' => 'nullable|array',
            'assignments' => 'sometimes|array',
            'assignments.*.assignment_type' => 'required|string|in:admin,manager,finance,procurement,user,hr',
            'assignments.*.user_id' => 'required_if:assignments.*.assignment_type,user,finance,hr|nullable|integer|exists:users,id',
            'assignments.*.is_required' => 'boolean'
        ]);

        // Custom validation for assignments when auto_approve is false
        $validator->after(function ($validator) use ($request) {
            if (!$request->auto_approve && $request->has('assignments') && (!$request->assignments || count($request->assignments) === 0)) {
                $validator->errors()->add('assignments', 'At least one assignment is required when auto approve is disabled.');
            }
        });

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            $stepData = $request->only([
                'name', 'description', 'order_index', 'is_active',
                'step_type', 'step_category', 'timeout_hours', 'auto_approve', 'conditions'
            ]);
            $stepData['is_required'] = true; // All steps are required by default

            $step->update($stepData);

            if ($request->has('assignments') && !$request->auto_approve) {
                $existingIds = collect($request->assignments)
                    ->pluck('id')
                    ->filter()
                    ->toArray();

                $step->assignments()->whereNotIn('id', $existingIds)->delete();

                foreach ($request->assignments as $assignmentData) {
                    $assignableType = $this->getAssignableTypeWithContext($assignmentData);
                    $assignableId = $this->getAssignableId($assignmentData, $assignableType);

                    if (isset($assignmentData['id'])) {
                        $assignment = WorkflowStepAssignment::find($assignmentData['id']);
                        if ($assignment) {
                            $assignment->update([
                                'assignable_type' => $assignableType,
                                'assignable_id' => $assignableId,
                                'is_required' => $assignmentData['is_required'] ?? true,
                                'conditions' => $assignmentData['conditions'] ?? null
                            ]);
                        }
                    } else {
                        WorkflowStepAssignment::create([
                            'workflow_step_id' => $step->id,
                            'assignable_type' => $assignableType,
                            'assignable_id' => $assignableId,
                            'is_required' => $assignmentData['is_required'] ?? true,
                            'conditions' => $assignmentData['conditions'] ?? null
                        ]);
                    }
                }
            } elseif ($request->auto_approve) {
                // If auto_approve is enabled, remove all existing assignments
                $step->assignments()->delete();
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Workflow step updated successfully',
                'data' => $step->load('assignments.assignable')
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to update workflow step',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified workflow step
     */
    public function destroy(string $id): JsonResponse
    {
        try {
            $step = WorkflowStep::findOrFail($id);
            $step->delete();

            return response()->json([
                'success' => true,
                'message' => 'Workflow step deleted successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete workflow step',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reorder workflow steps
     */
    public function reorder(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'step_ids' => 'required|array',
            'step_ids.*' => 'integer|exists:workflow_steps,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            // Update order_index for each step based on its position in the array
            foreach ($request->step_ids as $index => $stepId) {
                WorkflowStep::where('id', $stepId)->update(['order_index' => $index]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Workflow steps reordered successfully'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to reorder workflow steps',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get available assignable entities
     */
    public function getAssignableEntities(): JsonResponse
    {
        $users = User::with(['department', 'role'])
            ->select('id', 'full_name as name', 'email', 'department_id', 'role_id')
            ->get()
            ->map(function($user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'department_id' => $user->department_id,
                    'department_name' => $user->department ? $user->department->name : null,
                    'role_id' => $user->role_id,
                    'role_name' => $user->role ? $user->role->name : null
                ];
            });
        $roles = Role::select('id', 'name', 'description')->get();
        $departments = Department::select('id', 'name', 'description')->get();

        return response()->json([
            'success' => true,
            'data' => [
                'users' => $users,
                'roles' => $roles,
                'departments' => $departments
            ]
        ]);
    }

    /**
     * Get workflow statistics and summary
     */
    public function getStats(): JsonResponse
    {
        try {
            $totalSteps = WorkflowStep::count();
            $activeSteps = WorkflowStep::where('is_active', true)->count();
            $inactiveSteps = WorkflowStep::where('is_active', false)->count();

            $stepsByType = WorkflowStep::selectRaw('step_type, COUNT(*) as count')
                ->groupBy('step_type')
                ->get()
                ->pluck('count', 'step_type');

            $stepsWithAssignments = WorkflowStep::whereHas('assignments')->count();
            $stepsWithoutAssignments = WorkflowStep::whereDoesntHave('assignments')->count();

            $recentSteps = WorkflowStep::with(['assignments.assignable'])
                ->orderBy('updated_at', 'desc')
                ->limit(5)
                ->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'total_steps' => $totalSteps,
                    'active_steps' => $activeSteps,
                    'inactive_steps' => $inactiveSteps,
                    'steps_by_type' => $stepsByType,
                    'steps_with_assignments' => $stepsWithAssignments,
                    'steps_without_assignments' => $stepsWithoutAssignments,
                    'recent_steps' => $recentSteps
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch workflow statistics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get workflow steps summary for settings page
     */
    public function getSummary(): JsonResponse
    {
        try {
            $steps = WorkflowStep::with(['assignments.assignable'])
                ->orderBy('order_index')
                ->get()
                ->map(function($step) {
                    return [
                        'id' => $step->id,
                        'name' => $step->name,
                        'description' => $step->description,
                        'order_index' => $step->order_index,
                        'is_active' => $step->is_active,
                        'is_required' => $step->is_required,
                        'step_type' => $step->step_type,
                        'timeout_hours' => $step->timeout_hours,
                        'auto_approve' => $step->auto_approve,
                        'assignments_count' => $step->assignments->count(),
                        'assignments' => $step->assignments->map(function($assignment) {
                            $assignmentType = $this->getAssignmentTypeFromAssignable($assignment);
                            return [
                                'id' => $assignment->id,
                                'assignment_type' => $assignmentType,
                                'user_id' => in_array($assignmentType, ['user', 'finance']) ? $this->getUserIdFromAssignment($assignment) : null,
                                'is_required' => $assignment->is_required,
                                'assignable_name' => $this->getAssignableName($assignment)
                            ];
                        })
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => $steps
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch workflow summary',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get assignable name for display
     */
    private function getAssignableName($assignment): string
    {
        try {
            $assignable = $assignment->assignable;

            if (!$assignable) {
                return 'Unknown Assignment';
            }

            switch ($assignment->assignable_type) {
                case 'App\\Models\\User':
                    return $assignable->full_name ?? 'Unknown User';
                case 'App\\Models\\FinanceAssignment':
                    return $assignable->user ?
                        $assignable->user->full_name . ' (Finance)' : 'Unknown Finance User';
                case 'App\\Models\\Role':
                    return $assignable->name ?? 'Unknown Role';
                case 'App\\\\Models\\\\Department':
                    return $assignable->name ?? 'Unknown Department';
                default:
                    return 'Unknown';
            }
        } catch (\Exception $e) {
            return 'Unknown Assignment';
        }
    }

    /**
     * Get user ID from assignment
     */
    private function getUserIdFromAssignment($assignment): ?int
    {
        $assignable = $assignment->assignable;

        if (!$assignable) {
            return null;
        }

        switch ($assignment->assignable_type) {
            case 'App\\Models\\User':
                return $assignable->id;
            case 'App\\Models\\FinanceAssignment':
                return $assignable->user_id;
            case 'App\\Models\\Role':
                // For leave requests with role assignments, we don't return a user_id
                // as it should be selected by the user in the frontend
                $workflowStep = WorkflowStep::find($assignment->workflow_step_id);
                if ($workflowStep && $workflowStep->step_category === 'leave') {
                    return null;
                }
                return null;
            default:
                return null;
        }
    }

    /**
     * Get assignable type based on assignment type
     */
    private function getAssignableType($assignmentType): string
    {
        switch ($assignmentType) {
            case 'user':
            case 'hr':
                return 'App\\Models\\User';
            case 'finance':
                return 'App\\Models\\FinanceAssignment';
            case 'admin':
            case 'manager':
            case 'procurement':
                return 'App\\Models\\Role';
            case 'department':
                return 'App\\\\Models\\\\Department';
            default:
                return 'App\\Models\\Role';
        }
    }

    /**
     * Get assignable type based on assignment data (with context)
     */
    private function getAssignableTypeWithContext($assignmentData): string
    {
        $assignmentType = $assignmentData['assignment_type'];

        switch ($assignmentType) {
            case 'user':
            case 'hr':
                return 'App\\Models\\User';
            case 'finance':
                return 'App\\Models\\FinanceAssignment';
            case 'admin':
                // For admin assignments with specific user_id, store as User
                if (isset($assignmentData['user_id']) && !empty($assignmentData['user_id'])) {
                    return 'App\\Models\\User';
                }
                // Otherwise, store as Role
                return 'App\\Models\\Role';
            case 'manager':
            case 'procurement':
                return 'App\\Models\\Role';
            case 'department':
                return 'App\\\\Models\\\\Department';
            default:
                return 'App\\Models\\Role';
        }
    }

    /**
     * Get assignable ID based on assignment data
     */
    private function getAssignableId($assignmentData, $assignableType)
    {
        $assignmentType = $assignmentData['assignment_type'];

        switch ($assignmentType) {
            case 'user':
                return $assignmentData['user_id'] ?? null;
            case 'finance':
                // Create or find FinanceAssignment
                if ($assignableType === 'App\\Models\\FinanceAssignment') {
                    $financeAssignment = FinanceAssignment::firstOrCreate([
                        'user_id' => $assignmentData['user_id']
                    ], [
                        'is_active' => true
                    ]);
                    return $financeAssignment->id;
                }
                return $assignmentData['user_id'] ?? null;
            case 'admin':
                // For leave requests with specific admin user, store as user assignment
                if (isset($assignmentData['user_id']) && !empty($assignmentData['user_id'])) {
                    return $assignmentData['user_id'];
                }
                // Otherwise, store as role assignment
                $role = Role::where('name', $assignmentType)->first();
                return $role ? $role->id : null;
            case 'manager':
            case 'procurement':
                // Find role by name
                $role = Role::where('name', $assignmentType)->first();
                return $role ? $role->id : null;
            case 'hr':
                // For hr in leave requests, assign to specific user
                return $assignmentData['user_id'] ?? null;
            case 'department':
                return $assignmentData['department_id'] ?? null;
            default:
                return null;
        }
    }

    /**
     * Get assignment type from assignable relationship
     */
    private function getAssignmentTypeFromAssignable($assignment): string
    {
        switch ($assignment->assignable_type) {
            case 'App\\Models\\User':
                // For leave requests, we need to determine if this is admin, hr, or regular user
                $workflowStep = WorkflowStep::find($assignment->workflow_step_id);
                if ($workflowStep && $workflowStep->step_category === 'leave') {
                    $user = User::with('role')->find($assignment->assignable_id);
                    if ($user && $user->role) {
                        if ($user->role->name === 'admin') {
                            return 'admin';
                        } else {
                            // Check if user is from HR department
                            $user = User::with('department')->find($assignment->assignable_id);
                            if ($user && $user->department) {
                                $deptName = strtolower($user->department->name);
                                if (strpos($deptName, 'hr') !== false ||
                                    strpos($deptName, 'human') !== false ||
                                    strpos($deptName, 'resource') !== false ||
                                    strpos($deptName, 'منابع') !== false ||
                                    strpos($deptName, 'انسانی') !== false) {
                                    return 'hr';
                                }
                            }
                            return 'admin'; // Default fallback for leave requests
                        }
                    }
                    return 'admin'; // Default fallback if user not found
                }
                return 'user';
            case 'App\\Models\\FinanceAssignment':
                return 'finance';
            case 'App\\Models\\Role':
                $role = Role::find($assignment->assignable_id);
                return $role ? $role->name : 'admin';
            case 'App\\\\Models\\\\Department':
                return 'department';
            default:
                return 'admin';
        }
    }
}
