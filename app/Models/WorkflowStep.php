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
        'timeout_hours',
        'auto_approve_if_condition_met'
    ];

    protected $attributes = [
        'is_required' => true,
        'is_active' => true,
        'auto_approve_if_condition_met' => false
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_required' => 'boolean',
        'conditions' => 'array',
        'auto_approve_if_condition_met' => 'boolean'
    ];

    /**
     * Get the assignments for this workflow step
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(WorkflowStepAssignment::class);
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
     * Get steps that should be executed for a given request
     */
    public static function getStepsForRequest($request)
    {
        $steps = self::getActiveSteps();
        $applicableSteps = [];

        foreach ($steps as $step) {
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
    public function getAssignedUsers()
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
                })->get();
                $users = $users->merge($roleUsers);
            } elseif ($assignment->assignable_type === 'App\\Models\\Department') {
                $deptUsers = User::where('department_id', $assignment->assignable_id)->get();
                $users = $users->merge($deptUsers);
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
}
