<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class WorkflowStepAssignment extends Model
{
    use HasFactory;

    protected $fillable = [
        'workflow_step_id',
        'assignable_type',
        'assignable_id',
        'is_required',
        'conditions'
    ];

    protected $casts = [
        'is_required' => 'boolean',
        'conditions' => 'array'
    ];

    /**
     * Get the workflow step that owns this assignment
     */
    public function workflowStep(): BelongsTo
    {
        return $this->belongsTo(WorkflowStep::class);
    }

    /**
     * Get the assignable entity (User, Role, Department, or FinanceAssignment)
     */
    public function assignable()
    {
        switch ($this->assignable_type) {
            case 'App\\Models\\User':
                return $this->belongsTo(User::class, 'assignable_id');
            case 'App\\Models\\FinanceAssignment':
                return $this->belongsTo(FinanceAssignment::class, 'assignable_id');
            case 'App\\Models\\Role':
                return $this->belongsTo(Role::class, 'assignable_id');
            case 'App\\Models\\Department':
                return $this->belongsTo(Department::class, 'assignable_id');
            default:
                return $this->morphTo();
        }
    }

    /**
     * Get the assignable entity with proper handling for FinanceAssignment
     */
    public function getAssignableAttribute()
    {
        switch ($this->assignable_type) {
            case 'App\\Models\\User':
                return User::find($this->assignable_id);
            case 'App\\Models\\FinanceAssignment':
                return FinanceAssignment::with('user')->find($this->assignable_id);
            case 'App\\Models\\Role':
                return Role::find($this->assignable_id);
            case 'App\\Models\\Department':
                return Department::find($this->assignable_id);
            default:
                return null;
        }
    }

    /**
     * Get users based on assignment type
     */
    public function getUsers()
    {
        switch ($this->assignable_type) {
            case 'App\\Models\\User':
                return collect([User::find($this->assignable_id)])->filter();

            case 'App\\Models\\FinanceAssignment':
                $financeAssignment = FinanceAssignment::find($this->assignable_id);
                return $financeAssignment ? collect([$financeAssignment->user])->filter() : collect();

            case 'App\\Models\\Role':
                return User::whereHas('role', function($query) {
                    $query->where('id', $this->assignable_id);
                })->get();

            case 'App\\Models\\Department':
                return User::where('department_id', $this->assignable_id)->get();

            default:
                return collect();
        }
    }

    /**
     * Check if assignment conditions are met for a request
     */
    public function conditionsMet($request)
    {
        if (empty($this->conditions)) {
            return true;
        }

        foreach ($this->conditions as $condition) {
            if (!$this->evaluateCondition($condition, $request)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Evaluate a single condition
     */
    private function evaluateCondition($condition, $request)
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
}
