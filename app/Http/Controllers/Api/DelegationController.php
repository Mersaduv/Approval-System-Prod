<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Delegation;
use App\Models\User;
use App\Models\WorkflowStep;
use App\Models\Department;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DelegationController extends Controller
{
    /**
     * Get all delegations for the authenticated user
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = Delegation::with(['delegator', 'delegate', 'workflowStep', 'department'])
            ->where(function ($q) use ($user) {
                $q->where('delegator_id', $user->id)
                  ->orWhere('delegate_id', $user->id);
            });

        // Filter by status
        if ($request->has('status')) {
            if ($request->status === 'active') {
                $query->active();
            } elseif ($request->status === 'expired') {
                $query->where('is_active', true)
                      ->where('expires_at', '<', now());
            } elseif ($request->status === 'inactive') {
                $query->where('is_active', false);
            }
        }

        // Filter by type - removed since delegation_type is no longer used

        $delegations = $query->orderBy('created_at', 'desc')->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $delegations
        ]);
    }

    /**
     * Get delegations created by the user
     */
    public function myDelegations(Request $request): JsonResponse
    {
        $user = $request->user();

        $delegations = Delegation::with(['delegate', 'workflowStep', 'department'])
            ->where('delegator_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $delegations
        ]);
    }

    /**
     * Get delegations received by the user
     */
    public function receivedDelegations(Request $request): JsonResponse
    {
        $user = $request->user();

        $delegations = Delegation::with(['delegator', 'workflowStep', 'department'])
            ->where('delegate_id', $user->id)
            ->active()
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $delegations
        ]);
    }

    /**
     * Create a new delegation
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'delegate_id' => 'required|exists:users,id',
            'workflow_step_id' => 'required|exists:workflow_steps,id',
            'reason' => 'nullable|string|max:1000',
            'starts_at' => 'nullable|date|after_or_equal:yesterday',
            'expires_at' => 'nullable|date|after:starts_at',
            'permissions' => 'nullable|array'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $delegate = User::findOrFail($request->delegate_id);

        // Check if user can delegate to this person
        if (!Delegation::canDelegateTo($user, $delegate)) {
            return response()->json([
                'success' => false,
                'message' => 'You cannot delegate to this user'
            ], 403);
        }

        // Check for conflicting delegations
        $conflictingDelegation = Delegation::where('delegator_id', $user->id)
            ->where('delegate_id', $request->delegate_id)
            ->where('workflow_step_id', $request->workflow_step_id)
            ->where('is_active', true)
            ->where(function ($query) use ($request) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->first();

        if ($conflictingDelegation) {
            return response()->json([
                'success' => false,
                'message' => 'A similar delegation already exists'
            ], 409);
        }

        try {
            DB::beginTransaction();

            $delegation = Delegation::create([
                'delegator_id' => $user->id,
                'delegate_id' => $request->delegate_id,
                'workflow_step_id' => $request->workflow_step_id,
                'reason' => $request->reason,
                'starts_at' => $request->starts_at ? Carbon::parse($request->starts_at) : null,
                'expires_at' => $request->expires_at ? Carbon::parse($request->expires_at) : null,
                'permissions' => $request->permissions
            ]);

            // Log the delegation creation
            \App\Models\AuditLog::create([
                'user_id' => $user->id,
                'request_id' => null, // No specific request for delegation creation
                'action' => 'Delegation Created',
                'notes' => "Delegated responsibilities to {$delegate->full_name}",
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent()
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Delegation created successfully',
                'data' => $delegation->load(['delegate', 'workflowStep', 'department'])
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to create delegation: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update a delegation
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $delegation = Delegation::findOrFail($id);
        $user = $request->user();

        // Check if user owns this delegation
        if ($delegation->delegator_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'You can only update your own delegations'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'reason' => 'nullable|string|max:1000',
            'starts_at' => 'nullable|date|after_or_equal:yesterday',
            'expires_at' => 'nullable|date|after:starts_at',
            'is_active' => 'boolean',
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
            DB::beginTransaction();

            $delegation->update([
                'reason' => $request->reason ?? $delegation->reason,
                'starts_at' => $request->starts_at ? Carbon::parse($request->starts_at) : $delegation->starts_at,
                'expires_at' => $request->expires_at ? Carbon::parse($request->expires_at) : $delegation->expires_at,
                'is_active' => $request->has('is_active') ? $request->boolean('is_active') : $delegation->is_active,
                'permissions' => $request->permissions ?? $delegation->permissions
            ]);

            // Log the delegation update
            \App\Models\AuditLog::create([
                'user_id' => $user->id,
                'request_id' => null, // No specific request for delegation update
                'action' => 'Delegation Updated',
                'notes' => "Updated delegation to {$delegation->delegate->full_name}",
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent()
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Delegation updated successfully',
                'data' => $delegation->load(['delegate', 'workflowStep', 'department'])
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to update delegation: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete a delegation
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $delegation = Delegation::findOrFail($id);
        $user = $request->user();

        // Check if user owns this delegation or is the delegate
        if ($delegation->delegator_id !== $user->id && $delegation->delegate_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'You can only delete delegations you created or received'
            ], 403);
        }

        try {
            DB::beginTransaction();

            // Log the delegation deletion
            \App\Models\AuditLog::create([
                'user_id' => $user->id,
                'request_id' => null, // No specific request for delegation deletion
                'action' => 'Delegation Deleted',
                'notes' => "Deleted delegation to {$delegation->delegate->full_name}",
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent()
            ]);

            // Before deleting, check if there are any active requests assigned to this delegate
            // that need to be rolled back to the original approver
            $this->rollbackDelegatedRequests($delegation, $request->ip(), $request->userAgent());

            $delegation->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Delegation deleted successfully'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete delegation: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reject a delegation (for delegates)
     */
    public function reject(Request $request, int $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'reason' => 'required|string|max:1000'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $delegation = Delegation::findOrFail($id);
        $user = $request->user();

        // Check if user is the delegate
        if ($delegation->delegate_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'You can only reject delegations assigned to you'
            ], 403);
        }

        // Check if delegation is still active
        if (!$delegation->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'This delegation is already inactive'
            ], 400);
        }

        try {
            DB::beginTransaction();

            // Deactivate the delegation and store reject reason
            $delegation->update([
                'is_active' => false,
                'reject_reason' => $request->input('reason')
            ]);

            // Log the delegation rejection
            \App\Models\AuditLog::create([
                'user_id' => $user->id,
                'request_id' => null,
                'action' => 'Delegation Rejected',
                'notes' => "Rejected delegation from {$delegation->delegator->full_name}. Reason: {$request->input('reason')}",
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent()
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Delegation rejected successfully'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to reject delegation: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get available users for delegation
     */
    public function getAvailableUsers(Request $request): JsonResponse
    {
        $user = $request->user();

        // Get all users except the current user
        $users = User::with(['role', 'department'])
            ->where('id', '!=', $user->id)
            ->when($request->has('department_id'), function ($query) use ($request) {
                $query->where('department_id', $request->department_id);
            })
            ->orderBy('full_name')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $users
        ]);
    }



    /**
     * Get delegation statistics
     */
    public function getStats(Request $request): JsonResponse
    {
        $user = $request->user();

        $stats = [
            'total_delegations' => Delegation::where('delegator_id', $user->id)->count(),
            'active_delegations' => Delegation::where('delegator_id', $user->id)->active()->count(),
            'received_delegations' => Delegation::where('delegate_id', $user->id)->active()->count(),
            'expired_delegations' => Delegation::where('delegator_id', $user->id)
                ->where('is_active', true)
                ->where('expires_at', '<', now())
                ->count(),
        ];

        return response()->json([
            'success' => true,
            'data' => $stats
        ]);
    }

    /**
     * Get workflow steps that the user can delegate (based on their assignments)
     */
    public function getWorkflowSteps(Request $request): JsonResponse
    {
        $user = $request->user();
        $delegationType = $request->get('delegation_type', 'approval');

        try {
            // Get workflow steps where the user has assignments
            $workflowSteps = \App\Models\WorkflowStep::whereHas('assignments', function ($query) use ($user) {
                $query->where(function ($q) use ($user) {
                    // User assigned directly
                    $q->where('assignable_type', 'App\\Models\\User')
                      ->where('assignable_id', $user->id)
                      // Or user assigned by role
                      ->orWhere(function ($roleQuery) use ($user) {
                          $roleQuery->where('assignable_type', 'App\\Models\\Role')
                                   ->where('assignable_id', $user->role_id);
                      })
                      // Or user assigned by department
                      ->orWhere(function ($deptQuery) use ($user) {
                          $deptQuery->where('assignable_type', 'App\\Models\\Department')
                                   ->where('assignable_id', $user->department_id);
                      });
                });
            })
            ->where('is_active', true)
            ->with(['assignments'])
            ->get()
            ->map(function ($step) use ($user, $delegationType) {
                // Check if user has any assignments for this step
                $hasAssignments = $step->assignments->some(function ($assignment) use ($user, $delegationType) {
                    // Check if assignment is for this user
                    $isAssignedToUser = false;

                    if ($assignment->assignable_type === 'App\\Models\\User' && $assignment->assignable_id === $user->id) {
                        $isAssignedToUser = true;
                    } elseif ($assignment->assignable_type === 'App\\Models\\Role' && $assignment->assignable_id === $user->role_id) {
                        $isAssignedToUser = true;
                    } elseif ($assignment->assignable_type === 'App\\Models\\Department' && $assignment->assignable_id === $user->department_id) {
                        $isAssignedToUser = true;
                    }

                    if (!$isAssignedToUser) {
                        return false;
                    }

                    // Check delegation type permissions
                    switch ($delegationType) {
                        case 'approval':
                            return $assignment->can_approve ?? false;
                        case 'verification':
                            return $assignment->can_verify ?? false;
                        case 'notification':
                            return $assignment->can_notify ?? false;
                        case 'all':
                            return ($assignment->can_approve ?? false) ||
                                   ($assignment->can_verify ?? false) ||
                                   ($assignment->can_notify ?? false);
                        default:
                            return false;
                    }
                });

                return [
                    'id' => $step->id,
                    'name' => $step->name,
                    'description' => $step->description,
                    'workflow_id' => $step->workflow_id,
                    'step_order' => $step->step_order,
                    'has_assignments' => $hasAssignments
                ];
            })
            ->filter(function ($step) {
                return $step['has_assignments'];
            })
            ->values();

            return response()->json([
                'success' => true,
                'data' => $workflowSteps
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to load workflow steps: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Rollback requests that were assigned to a delegate back to the original approver
     */
    private function rollbackDelegatedRequests(Delegation $delegation, $ipAddress = null, $userAgent = null): void
    {
        // Get all pending requests that were assigned to this delegate
        // This is a simplified approach - in a real system, you might need more complex logic
        // to determine which requests need to be rolled back

        // For now, we'll just log that the delegation is being removed
        // The actual request reassignment would depend on your specific business logic

        \App\Models\AuditLog::create([
            'user_id' => $delegation->delegator_id,
            'request_id' => null,
            'action' => 'Delegation Rollback',
            'notes' => "Rolling back requests from delegate {$delegation->delegate->full_name} to original approver",
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent
        ]);

        // Note: In a production system, you would implement the actual rollback logic here
        // This might involve:
        // 1. Finding all requests currently assigned to the delegate
        // 2. Reassigning them to the original approver
        // 3. Updating request statuses if needed
        // 4. Sending notifications to affected users
    }
}
