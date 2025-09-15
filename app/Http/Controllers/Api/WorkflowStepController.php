<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\WorkflowStep;
use App\Models\WorkflowStepAssignment;
use App\Models\User;
use App\Models\Role;
use App\Models\Department;
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
    public function index(): JsonResponse
    {
        $steps = WorkflowStep::with(['assignments.assignable'])
            ->orderBy('order_index')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $steps
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
            'timeout_hours' => 'nullable|integer|min:1',
            'auto_approve_if_condition_met' => 'boolean',
            'conditions' => 'nullable|array',
            'assignments' => 'nullable|array'
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

            $stepData = $request->only([
                'name', 'description', 'order_index', 'is_active',
                'step_type', 'timeout_hours', 'auto_approve_if_condition_met', 'conditions'
            ]);
            $stepData['is_required'] = true; // All steps are required by default

            $step = WorkflowStep::create($stepData);

            if ($request->has('assignments')) {
                foreach ($request->assignments as $assignmentData) {
                    $assignableType = $this->getAssignableType($assignmentData['assignment_type']);
                    $assignableId = $this->getAssignableId($assignmentData);

                    WorkflowStepAssignment::create([
                        'workflow_step_id' => $step->id,
                        'assignable_type' => $assignableType,
                        'assignable_id' => $assignableId,
                        'is_required' => $assignmentData['is_required'] ?? true,
                        'priority' => $assignmentData['priority'] ?? 1,
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
            'timeout_hours' => 'nullable|integer|min:1',
            'auto_approve_if_condition_met' => 'boolean',
            'conditions' => 'nullable|array',
            'assignments' => 'nullable|array'
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

            $stepData = $request->only([
                'name', 'description', 'order_index', 'is_active',
                'step_type', 'timeout_hours', 'auto_approve_if_condition_met', 'conditions'
            ]);
            $stepData['is_required'] = true; // All steps are required by default

            $step->update($stepData);

            if ($request->has('assignments')) {
                $existingIds = collect($request->assignments)
                    ->pluck('id')
                    ->filter()
                    ->toArray();

                $step->assignments()->whereNotIn('id', $existingIds)->delete();

                foreach ($request->assignments as $assignmentData) {
                    $assignableType = $this->getAssignableType($assignmentData['assignment_type']);
                    $assignableId = $this->getAssignableId($assignmentData);

                    if (isset($assignmentData['id'])) {
                        $assignment = WorkflowStepAssignment::find($assignmentData['id']);
                        if ($assignment) {
                            $assignment->update([
                                'assignable_type' => $assignableType,
                                'assignable_id' => $assignableId,
                                'is_required' => $assignmentData['is_required'] ?? true,
                                'priority' => $assignmentData['priority'] ?? 1,
                                'conditions' => $assignmentData['conditions'] ?? null
                            ]);
                        }
                    } else {
                        WorkflowStepAssignment::create([
                            'workflow_step_id' => $step->id,
                            'assignable_type' => $assignableType,
                            'assignable_id' => $assignableId,
                            'is_required' => $assignmentData['is_required'] ?? true,
                            'priority' => $assignmentData['priority'] ?? 1,
                            'conditions' => $assignmentData['conditions'] ?? null
                        ]);
                    }
                }
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
            WorkflowStep::reorderSteps($request->step_ids);

            return response()->json([
                'success' => true,
                'message' => 'Workflow steps reordered successfully'
            ]);

        } catch (\Exception $e) {
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
        $users = User::select('id', 'full_name as name', 'email')->get();
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
                        'auto_approve_if_condition_met' => $step->auto_approve_if_condition_met,
                        'assignments_count' => $step->assignments->count(),
                        'assignments' => $step->assignments->map(function($assignment) {
                            $assignmentType = $this->getAssignmentTypeFromAssignable($assignment);
                            return [
                                'id' => $assignment->id,
                                'assignment_type' => $assignmentType,
                                'user_id' => $assignmentType === 'user' ? $assignment->assignable_id : null,
                                'is_required' => $assignment->is_required,
                                'priority' => $assignment->priority,
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
            switch ($assignment->assignable_type) {
                case 'App\\Models\\User':
                    $user = User::find($assignment->assignable_id);
                    return $user ? $user->full_name : 'Unknown User';
                case 'App\\Models\\Role':
                    $role = Role::find($assignment->assignable_id);
                    return $role ? $role->name : 'Unknown Role';
                case 'App\\\\Models\\\\Department':
                    $department = Department::find($assignment->assignable_id);
                    return $department ? $department->name : 'Unknown Department';
                default:
                    return 'Unknown';
            }
        } catch (\Exception $e) {
            return 'Unknown';
        }
    }

    /**
     * Get assignable type based on assignment type
     */
    private function getAssignableType($assignmentType): string
    {
        switch ($assignmentType) {
            case 'user':
                return 'App\\Models\\User';
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
     * Get assignable ID based on assignment data
     */
    private function getAssignableId($assignmentData)
    {
        $assignmentType = $assignmentData['assignment_type'];

        switch ($assignmentType) {
            case 'user':
                return $assignmentData['user_id'] ?? null;
            case 'admin':
            case 'manager':
            case 'procurement':
                // Find role by name
                $role = Role::where('name', $assignmentType)->first();
                return $role ? $role->id : null;
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
                return 'user';
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
