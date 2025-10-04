<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\ApprovalToken;
use App\Models\Request as RequestModel;
use App\Services\WorkflowService;
use Illuminate\Support\Facades\Validator;

class ApprovalPortalController extends Controller
{
    protected $workflowService;

    public function __construct(WorkflowService $workflowService)
    {
        $this->workflowService = $workflowService;
    }

    /**
     * Display the approval portal
     */
    public function show(string $token)
    {
        $approvalToken = ApprovalToken::where('token', $token)
            ->with(['request.employee.department', 'approver'])
            ->first();

        if (!$approvalToken) {
            return view('approval.invalid-token', [
                'message' => 'Invalid approval token'
            ]);
        }

        if (!$approvalToken->isValid()) {
            $message = 'This approval link has expired or has already been used.';
            if ($approvalToken->isExpired()) {
                $message = 'This approval link has expired.';
            } elseif ($approvalToken->isUsed()) {
                $message = 'This approval link has already been used.';
            }

            return view('approval.invalid-token', [
                'message' => $message
            ]);
        }

        return view('approval.portal', [
            'request' => $approvalToken->request,
            'approver' => $approvalToken->approver,
            'approvalToken' => $approvalToken,
            'token' => $token
        ]);
    }

    /**
     * Process approval action
     */
    public function process(Request $request, string $token): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'action' => 'required|in:approve,reject,forward',
            'notes' => 'nullable|string|max:1000',
            'forward_to' => 'nullable|exists:users,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $approvalToken = ApprovalToken::where('token', $token)->first();

        if (!$approvalToken || !$approvalToken->isValid()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired approval token'
            ], 400);
        }

        try {
            $requestModel = $approvalToken->request;
            $approver = $approvalToken->approver;

            switch ($request->action) {
                case 'approve':
                    $this->workflowService->approveRequest(
                        $requestModel->id,
                        $approver->id,
                        $request->notes
                    );
                    $message = 'Request approved successfully';
                    break;

                case 'reject':
                    $this->workflowService->rejectRequest(
                        $requestModel->id,
                        $approver->id,
                        $request->notes ?? 'No reason provided'
                    );
                    $message = 'Request rejected successfully';
                    break;

                case 'forward':
                    // For now, we'll treat forward as approve with a note
                    // In a more complex system, this would forward to another approver
                    $this->workflowService->approveRequest(
                        $requestModel->id,
                        $approver->id,
                        'Forwarded: ' . ($request->notes ?? 'No notes provided')
                    );
                    $message = 'Request forwarded successfully';
                    break;
            }

            // Mark token as used
            $approvalToken->markAsUsed();

            return response()->json([
                'success' => true,
                'message' => $message,
                'redirect' => url("/approval/{$token}/success")
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to process approval',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Show success page
     */
    public function success(string $token)
    {
        $approvalToken = ApprovalToken::where('token', $token)->first();

        if (!$approvalToken) {
            return view('approval.invalid-token', [
                'message' => 'Invalid approval token'
            ]);
        }

        return view('approval.success', [
            'request' => $approvalToken->request,
            'approver' => $approvalToken->approver
        ]);
    }

    /**
     * Get request details for API
     */
    public function getRequestDetails(string $token): JsonResponse
    {
        $approvalToken = ApprovalToken::where('token', $token)
            ->with(['request.employee.department', 'approver'])
            ->first();

        if (!$approvalToken || !$approvalToken->isValid()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired approval token'
            ], 400);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'request' => $approvalToken->request,
                'approver' => $approvalToken->approver,
                'expires_at' => $approvalToken->expires_at,
                'is_valid' => $approvalToken->isValid()
            ]
        ]);
    }
}
