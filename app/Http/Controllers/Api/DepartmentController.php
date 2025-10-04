<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Department;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class DepartmentController extends Controller
{
    /**
     * Get departments by role
     */
    public function getByRole(Request $request): JsonResponse
    {
        $roleId = $request->get('role_id');

        if (!$roleId) {
            return response()->json([
                'success' => false,
                'message' => 'Role ID is required'
            ], 400);
        }

        $departments = Department::whereHas('roles', function($query) use ($roleId) {
            $query->where('roles.id', $roleId);
        })
        ->with('roles')
        ->orderBy('name')
        ->get();

        return response()->json([
            'success' => true,
            'data' => $departments
        ]);
    }
}
