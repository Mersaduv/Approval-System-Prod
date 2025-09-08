<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\ApprovalRule;
use App\Models\Department;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class ApprovalRuleController extends Controller
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
     * Display a listing of approval rules
     */
    public function index(Request $request): JsonResponse
    {
        $query = ApprovalRule::with('department');

        // Filter by department
        if ($request->has('department_id')) {
            $query->where('department_id', $request->department_id);
        }

        // Filter by approver role
        if ($request->has('approver_role')) {
            $query->where('approver_role', $request->approver_role);
        }

        $rules = $query->orderBy('department_id')
            ->orderBy('order')
            ->orderBy('min_amount')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $rules
        ]);
    }

    /**
     * Store a newly created approval rule
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'department_id' => 'required|exists:departments,id',
            'min_amount' => 'required|numeric|min:0',
            'max_amount' => 'required|numeric|gte:min_amount',
            'approver_role' => 'required|in:Employee,Manager,SalesManager,CEO,Procurement,Admin',
            'order' => 'required|integer|min:1'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Check for overlapping rules
        $overlapping = ApprovalRule::where('department_id', $request->department_id)
            ->where(function($query) use ($request) {
                $query->whereBetween('min_amount', [$request->min_amount, $request->max_amount])
                      ->orWhereBetween('max_amount', [$request->min_amount, $request->max_amount])
                      ->orWhere(function($q) use ($request) {
                          $q->where('min_amount', '<=', $request->min_amount)
                            ->where('max_amount', '>=', $request->max_amount);
                      });
            })
            ->exists();

        if ($overlapping) {
            return response()->json([
                'success' => false,
                'message' => 'Rule overlaps with existing rules for this department and amount range'
            ], 422);
        }

        try {
            $rule = ApprovalRule::create($request->all());

            return response()->json([
                'success' => true,
                'message' => 'Approval rule created successfully',
                'data' => $rule->load('department')
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create approval rule',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified approval rule
     */
    public function show(string $id): JsonResponse
    {
        $rule = ApprovalRule::with('department')->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $rule
        ]);
    }

    /**
     * Update the specified approval rule
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $rule = ApprovalRule::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'department_id' => 'required|exists:departments,id',
            'min_amount' => 'required|numeric|min:0',
            'max_amount' => 'required|numeric|gte:min_amount',
            'approver_role' => 'required|in:Employee,Manager,SalesManager,CEO,Procurement,Admin',
            'order' => 'required|integer|min:1'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Check for overlapping rules (excluding current rule)
        $overlapping = ApprovalRule::where('department_id', $request->department_id)
            ->where('id', '!=', $id)
            ->where(function($query) use ($request) {
                $query->whereBetween('min_amount', [$request->min_amount, $request->max_amount])
                      ->orWhereBetween('max_amount', [$request->min_amount, $request->max_amount])
                      ->orWhere(function($q) use ($request) {
                          $q->where('min_amount', '<=', $request->min_amount)
                            ->where('max_amount', '>=', $request->max_amount);
                      });
            })
            ->exists();

        if ($overlapping) {
            return response()->json([
                'success' => false,
                'message' => 'Rule overlaps with existing rules for this department and amount range'
            ], 422);
        }

        try {
            $rule->update($request->all());

            return response()->json([
                'success' => true,
                'message' => 'Approval rule updated successfully',
                'data' => $rule->load('department')
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update approval rule',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified approval rule
     */
    public function destroy(string $id): JsonResponse
    {
        try {
            $rule = ApprovalRule::findOrFail($id);
            $rule->delete();

            return response()->json([
                'success' => true,
                'message' => 'Approval rule deleted successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete approval rule',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get available approver roles
     */
    public function getApproverRoles(): JsonResponse
    {
        $roles = [
            'Employee' => 'Employee',
            'Manager' => 'Manager',
            'SalesManager' => 'Sales Manager',
            'CEO' => 'CEO',
            'Procurement' => 'Procurement',
            'Admin' => 'Admin'
        ];

        return response()->json([
            'success' => true,
            'data' => $roles
        ]);
    }

    /**
     * Get rules for a specific department
     */
    public function getDepartmentRules(string $departmentId): JsonResponse
    {
        $rules = ApprovalRule::where('department_id', $departmentId)
            ->orderBy('order')
            ->orderBy('min_amount')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $rules
        ]);
    }

    /**
     * Bulk update rules for a department
     */
    public function bulkUpdate(Request $request, string $departmentId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'rules' => 'required|array',
            'rules.*.min_amount' => 'required|numeric|min:0',
            'rules.*.max_amount' => 'required|numeric|gte:rules.*.min_amount',
            'rules.*.approver_role' => 'required|in:Employee,Manager,SalesManager,CEO,Procurement,Admin',
            'rules.*.order' => 'required|integer|min:1'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Delete existing rules for this department
            ApprovalRule::where('department_id', $departmentId)->delete();

            // Create new rules
            foreach ($request->rules as $ruleData) {
                $ruleData['department_id'] = $departmentId;
                ApprovalRule::create($ruleData);
            }

            return response()->json([
                'success' => true,
                'message' => 'Department rules updated successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update department rules',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
