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

        // Filter by type
        if ($request->has('type')) {
            $query->where('delegation_type', $request->type);
        }

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
            'delegation_type' => 'required|in:approval,verification,notification,all',
            'reason' => 'nullable|string|max:1000',
            'starts_at' => 'nullable|date|after_or_equal:now',
            'expires_at' => 'nullable|date|after:starts_at',
            'can_delegate_further' => 'boolean',
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
            ->where('delegation_type', $request->delegation_type)
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
                'delegation_type' => $request->delegation_type,
                'reason' => $request->reason,
                'starts_at' => $request->starts_at ? Carbon::parse($request->starts_at) : null,
                'expires_at' => $request->expires_at ? Carbon::parse($request->expires_at) : null,
                'can_delegate_further' => $request->boolean('can_delegate_further', false),
                'permissions' => $request->permissions
            ]);

            // Log the delegation creation
            \App\Models\AuditLog::create([
                'user_id' => $user->id,
                'action' => 'Delegation Created',
                'details' => "Delegated {$request->delegation_type} responsibilities to {$delegate->full_name}",
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
            'starts_at' => 'nullable|date',
            'expires_at' => 'nullable|date|after:starts_at',
            'is_active' => 'boolean',
            'can_delegate_further' => 'boolean',
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
                'can_delegate_further' => $request->has('can_delegate_further') ? $request->boolean('can_delegate_further') : $delegation->can_delegate_further,
                'permissions' => $request->permissions ?? $delegation->permissions
            ]);

            // Log the delegation update
            \App\Models\AuditLog::create([
                'user_id' => $user->id,
                'action' => 'Delegation Updated',
                'details' => "Updated delegation to {$delegation->delegate->full_name}",
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

        // Check if user owns this delegation
        if ($delegation->delegator_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'You can only delete your own delegations'
            ], 403);
        }

        try {
            DB::beginTransaction();

            // Log the delegation deletion
            \App\Models\AuditLog::create([
                'user_id' => $user->id,
                'action' => 'Delegation Deleted',
                'details' => "Deleted delegation to {$delegation->delegate->full_name}",
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent()
            ]);

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
     * Get workflow steps for delegation
     */
    public function getWorkflowSteps(Request $request): JsonResponse
    {
        $query = WorkflowStep::where('is_active', true)
            ->where('auto_approve', false) // Exclude auto-approval steps
            ->orderBy('order_index');

        // Filter by delegation type if provided
        if ($request->has('delegation_type')) {
            $delegationType = $request->delegation_type;

            if ($delegationType === 'approval') {
                $query->where('step_type', 'approval');
            } elseif ($delegationType === 'verification') {
                $query->where('step_type', 'verification');
            } elseif ($delegationType === 'notification') {
                $query->where('step_type', 'notification');
            }
            // For 'all' type, don't filter by step_type
        }

        $steps = $query->get();

        return response()->json([
            'success' => true,
            'data' => $steps
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
}
