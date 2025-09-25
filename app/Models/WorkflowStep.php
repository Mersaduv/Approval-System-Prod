<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkflowStep extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'order_index',
        'is_active',
        'is_required',
        'conditions',
        'step_type',
        'step_category',
        'timeout_hours',
        'auto_approve'
    ];

    protected $attributes = [
        'is_required' => true,
        'is_active' => true,
        'auto_approve' => false
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_required' => 'boolean',
        'conditions' => 'array',
        'auto_approve' => 'boolean'
    ];

    /**
     * Get the assignments for this workflow step
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(WorkflowStepAssignment::class);
    }

    /**
     * Boot the model
     */
    protected static function boot()
    {
        parent::boot();

        // Delete related assignments when step is deleted
        static::deleting(function ($step) {
            $step->assignments()->delete();
        });

    }

    /**
     * Get active workflow steps ordered by order_index
     */
    public static function getActiveSteps()
    {
        return self::where('is_active', true)
            ->orderBy('order_index')
            ->get();
    }

    /**
     * Get steps that should be executed for a given request (excluding auto approve steps)
     */
    public static function getStepsForRequest($request)
    {
        $steps = self::getActiveSteps();
        $applicableSteps = [];

        // Check if the request creator is admin or procurement
        $isAdminRequest = $request->employee && $request->employee->role &&
            in_array($request->employee->role->name, ['admin', 'procurement']);

        foreach ($steps as $step) {
            // Skip auto approve steps from workflow progress display
            if ($step->auto_approve) {
                continue;
            }

            // Skip manager assignment steps for admin requests
            if ($isAdminRequest && self::isManagerAssignmentStep($step)) {
                continue;
            }

            if (self::shouldExecuteStep($step, $request)) {
                $applicableSteps[] = $step;
            }
        }

        return collect($applicableSteps);
    }

    /**
     * Get all steps for a given request (including auto approve steps)
     */
    public static function getAllStepsForRequest($request)
    {
        $steps = self::getActiveSteps();
        $applicableSteps = [];

        // Check if the request creator is admin or procurement
        $isAdminRequest = $request->employee && $request->employee->role &&
            in_array($request->employee->role->name, ['admin', 'procurement']);

        foreach ($steps as $step) {
            // Skip manager assignment steps for admin requests
            if ($isAdminRequest && self::isManagerAssignmentStep($step)) {
                continue;
            }

            if (self::shouldExecuteStep($step, $request)) {
                $applicableSteps[] = $step;
            }
        }

        return collect($applicableSteps);
    }

    /**
     * Check if a step should be executed based on conditions
     */
    public static function shouldExecuteStep($step, $request)
    {
        if (!$step->is_active) {
            return false;
        }

        // If no conditions, always execute
        if (empty($step->conditions)) {
            return true;
        }

        // Check conditions
        foreach ($step->conditions as $condition) {
            if (!self::evaluateCondition($condition, $request)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Evaluate a single condition
     */
    private static function evaluateCondition($condition, $request)
    {
        $field = $condition['field'] ?? null;
        $operator = $condition['operator'] ?? null;
        $value = $condition['value'] ?? null;

        if (!$field || !$operator) {
            return true;
        }

        $requestValue = data_get($request, $field);

        switch ($operator) {
            case '>':
                return $requestValue > $value;
            case '>=':
                return $requestValue >= $value;
            case '<':
                return $requestValue < $value;
            case '<=':
                return $requestValue <= $value;
            case '=':
            case '==':
                return $requestValue == $value;
            case '!=':
                return $requestValue != $value;
            case 'in':
                return in_array($requestValue, (array)$value);
            case 'not_in':
                return !in_array($requestValue, (array)$value);
            default:
                return true;
        }
    }

    /**
     * Get assigned users for this step
     */
    public function getAssignedUsers($request = null)
    {
        $users = collect();

        foreach ($this->assignments as $assignment) {
            if ($assignment->assignable_type === 'App\\Models\\User') {
                $user = User::find($assignment->assignable_id);
                if ($user) {
                    $users->push($user);
                }
            } elseif ($assignment->assignable_type === 'App\\Models\\Role') {
                $roleUsers = User::whereHas('role', function($query) use ($assignment) {
                    $query->where('id', $assignment->assignable_id);
                });

                // If request is provided and role is manager, filter by department
                if ($request && $assignment->assignable_id == 2) { // Role ID 2 is manager
                    $roleUsers->where('department_id', $request->employee->department_id);
                }

                $users = $users->merge($roleUsers->get());
            } elseif ($assignment->assignable_type === 'App\\Models\\Department') {
                $deptUsers = User::where('department_id', $assignment->assignable_id)->get();
                $users = $users->merge($deptUsers);
            } elseif ($assignment->assignable_type === 'App\\Models\\FinanceAssignment') {
                $financeAssignment = \App\Models\FinanceAssignment::find($assignment->assignable_id);
                if ($financeAssignment && $financeAssignment->user) {
                    $users->push($financeAssignment->user);
                }
            }
        }

        return $users->unique('id');
    }

    /**
     * Reorder steps after a step is moved
     */
    public static function reorderSteps($stepIds)
    {
        foreach ($stepIds as $index => $stepId) {
            self::where('id', $stepId)->update(['order_index' => $index]);
        }
    }

    /**
     * Check if a step has manager assignments
     */
    public static function isManagerAssignmentStep($step): bool
    {
        return $step->assignments->some(function($assignment) {
            // Check if assignment is for manager role
            if ($assignment->assignable_type === 'App\\Models\\Role') {
                $role = \App\Models\Role::find($assignment->assignable_id);
                return $role && $role->name === 'manager';
            }

            // Check if assignment is for manager department
            if ($assignment->assignable_type === 'App\\Models\\Department') {
                $department = \App\Models\Department::find($assignment->assignable_id);
                return $department && $department->name === 'Management'; // Assuming management department name
            }

            // Check if assignment is for a specific manager user
            if ($assignment->assignable_type === 'App\\Models\\User') {
                $user = \App\Models\User::find($assignment->assignable_id);
                return $user && $user->role && $user->role->name === 'manager';
            }

            return false;
        });
    }
}
