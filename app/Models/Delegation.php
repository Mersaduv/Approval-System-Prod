<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class Delegation extends Model
{
    use HasFactory;

    protected $fillable = [
        'delegator_id',
        'delegate_id',
        'workflow_step_id',
        'department_id',
        'delegation_type',
        'reason',
        'starts_at',
        'expires_at',
        'is_active',
        'can_delegate_further',
        'permissions'
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'expires_at' => 'datetime',
        'is_active' => 'boolean',
        'can_delegate_further' => 'boolean',
        'permissions' => 'array'
    ];

    /**
     * Get the user who is delegating
     */
    public function delegator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'delegator_id');
    }

    /**
     * Get the user who receives the delegation
     */
    public function delegate(): BelongsTo
    {
        return $this->belongsTo(User::class, 'delegate_id');
    }

    /**
     * Get the workflow step (if specific)
     */
    public function workflowStep(): BelongsTo
    {
        return $this->belongsTo(WorkflowStep::class);
    }

    /**
     * Get the department (if specific)
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * Check if delegation is currently active
     */
    public function isCurrentlyActive(): bool
    {
        if (!$this->is_active) {
            return false;
        }

        $now = now();

        // Check if delegation has started
        if ($this->starts_at && $now->lt($this->starts_at)) {
            return false;
        }

        // Check if delegation has expired
        if ($this->expires_at && $now->gt($this->expires_at)) {
            return false;
        }

        return true;
    }

    /**
     * Check if delegation applies to a specific workflow step
     */
    public function appliesToWorkflowStep(?int $workflowStepId): bool
    {
        // If no specific workflow step is set, applies to all
        if (!$this->workflow_step_id) {
            return true;
        }

        return $this->workflow_step_id === $workflowStepId;
    }

    /**
     * Check if delegation applies to a specific department
     */
    public function appliesToDepartment(?int $departmentId): bool
    {
        // If no specific department is set, applies to all
        if (!$this->department_id) {
            return true;
        }

        return $this->department_id === $departmentId;
    }

    /**
     * Check if delegation applies to a specific request
     */
    public function appliesToRequest(Request $request): bool
    {
        // Check if delegation is active
        if (!$this->isCurrentlyActive()) {
            return false;
        }

        // Check workflow step applicability
        if (!$this->appliesToWorkflowStep(null)) {
            // For now, we'll check this when we know the specific step
            // This will be handled in the workflow service
        }

        // Check department applicability
        if (!$this->appliesToDepartment($request->employee->department_id)) {
            return false;
        }

        return true;
    }

    /**
     * Get active delegations for a user
     */
    public static function getActiveDelegationsForUser(int $userId): \Illuminate\Database\Eloquent\Collection
    {
        return self::where('delegate_id', $userId)
            ->where('is_active', true)
            ->where(function ($query) {
                $query->whereNull('starts_at')
                    ->orWhere('starts_at', '<=', now());
            })
            ->where(function ($query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->with(['delegator', 'workflowStep', 'department'])
            ->get();
    }

    /**
     * Get delegations created by a user
     */
    public static function getDelegationsByUser(int $userId): \Illuminate\Database\Eloquent\Collection
    {
        return self::where('delegator_id', $userId)
            ->with(['delegate', 'workflowStep', 'department'])
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Check if user can delegate to another user
     */
    public static function canDelegateTo(User $delegator, User $delegate): bool
    {
        // Can't delegate to self
        if ($delegator->id === $delegate->id) {
            return false;
        }

        // All users can delegate to any other user
        // This allows employees to delegate to managers, managers to other managers, etc.
        return true;
    }

    /**
     * Get effective approvers for a workflow step considering delegations
     */
    public static function getEffectiveApprovers(int $workflowStepId, int $departmentId = null): \Illuminate\Support\Collection
    {
        // Get original approvers from workflow step assignments
        $workflowStep = WorkflowStep::with('assignments.assignable')->find($workflowStepId);
        if (!$workflowStep) {
            return collect();
        }

        $originalApprovers = $workflowStep->getAssignedUsers();
        $effectiveApprovers = collect();

        foreach ($originalApprovers as $approver) {
            // Check if this approver has active delegations
            $activeDelegations = self::where('delegator_id', $approver->id)
                ->where('is_active', true)
                ->where(function ($query) use ($workflowStepId) {
                    $query->whereNull('workflow_step_id')
                        ->orWhere('workflow_step_id', $workflowStepId);
                })
                ->where(function ($query) use ($departmentId) {
                    $query->whereNull('department_id')
                        ->orWhere('department_id', $departmentId);
                })
                ->where(function ($query) {
                    $query->whereNull('starts_at')
                        ->orWhere('starts_at', '<=', now());
                })
                ->where(function ($query) {
                    $query->whereNull('expires_at')
                        ->orWhere('expires_at', '>', now());
                })
                ->get();

            if ($activeDelegations->isNotEmpty()) {
                // Add delegates instead of original approver
                foreach ($activeDelegations as $delegation) {
                    $effectiveApprovers->push($delegation->delegate);
                }
            } else {
                // No delegation, use original approver
                $effectiveApprovers->push($approver);
            }
        }

        return $effectiveApprovers->unique('id');
    }

    /**
     * Scope for active delegations
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('starts_at')
                    ->orWhere('starts_at', '<=', now());
            })
            ->where(function ($q) {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            });
    }

}
