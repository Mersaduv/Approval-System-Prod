<?php

namespace App\Services;

use App\Models\Request as RequestModel;
use App\Models\User;
use App\Models\Notification;
use App\Models\ApprovalToken;
use App\Mail\ApprovalNotificationMail;
use App\Mail\EmployeeNotificationMail;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class NotificationService
{
    /**
     * Send approval request notification to approver
     */
    public function sendApprovalRequest(RequestModel $request, User $approver, string $message): void
    {
        // Create approval token (never expires)
        $approvalToken = ApprovalToken::createToken($request->id, $approver->id, 'approve');

        // Send in-app notification
        $this->sendInAppNotification(
            $request,
            $approver->id,
            'Approval Required',
            $message
        );

        // Send email notification
        try {
            Mail::to($approver->email)->send(new ApprovalNotificationMail($request, $approver, $approvalToken, 'approve'));
        } catch (\Exception $e) {
            Log::error('Failed to send approval email: ' . $e->getMessage());
        }
    }

    /**
     * Send notification to employee about request status change
     */
    public function sendEmployeeNotification(RequestModel $request, string $status, string $reason = null): void
    {
        $employee = $request->employee;

        // Send in-app notification
        $message = $this->getEmployeeMessage($request, $status, $reason);
        $this->sendInAppNotification(
            $request,
            $employee->id,
            $this->getEmployeeTitle($status),
            $message
        );

        // Send email notification
        try {
            Mail::to($employee->email)->send(new EmployeeNotificationMail($request, $employee, $status, $reason));
        } catch (\Exception $e) {
            Log::error('Failed to send employee notification: ' . $e->getMessage());
        }
    }

    /**
     * Send notification to procurement team for verification
     */
    public function sendProcurementVerificationRequest(RequestModel $request): void
    {
        // Get procurement users
        $procurementUsers = User::whereHas('role', function($query) {
            $query->where('name', 'procurement');
        })->get();

        foreach ($procurementUsers as $user) {
            // Create approval token (never expires)
            $approvalToken = ApprovalToken::createToken($request->id, $user->id, 'approve');

            // Send in-app notification
            $this->sendInAppNotification(
                $request,
                $user->id,
                'Approval Required',
                "Approval required for step: Procurement Verification"
            );

            // Send email notification using simple template
            try {
                Mail::to($user->email)->send(new ApprovalNotificationMail($request, $user, $approvalToken, 'approve'));
            } catch (\Exception $e) {
                Log::error('Failed to send procurement verification email: ' . $e->getMessage());
            }
        }
    }

    /**
     * Send notification to procurement team
     */
    public function sendProcurementNotification(RequestModel $request): void
    {
        // For now, we'll notify admin users since we don't have a specific procurement role
        $adminUsers = User::whereHas('role', function($query) {
            $query->where('name', 'admin');
        })->get();

        foreach ($adminUsers as $user) {
            // Create approval token (never expires)
            $approvalToken = ApprovalToken::createToken($request->id, $user->id, 'approve');

            // Send in-app notification
            $this->sendInAppNotification(
                $request,
                $user->id,
                'Approval Required',
                "Approval required for step: Procurement Order"
            );

            // Send email notification using simple template
            try {
                Mail::to($user->email)->send(new ApprovalNotificationMail($request, $user, $approvalToken, 'approve'));
            } catch (\Exception $e) {
                Log::error('Failed to send procurement notification email: ' . $e->getMessage());
            }
        }
    }

    /**
     * Send general notification
     */
    public function sendNotification(RequestModel $request, int $receiverId, string $title, string $message, string $channel = 'InApp'): void
    {
        Notification::create([
            'request_id' => $request->id,
            'receiver_id' => $receiverId,
            'channel' => $channel,
            'message' => $message,
            'status' => 'Unread'
        ]);
    }

    /**
     * Send in-app notification
     */
    public function sendInAppNotification(RequestModel $request, int $receiverId, string $title, string $message): void
    {
        $this->sendNotification($request, $receiverId, $title, $message, 'InApp');
    }

    /**
     * Send workflow step update notification to request creator
     */
    public function sendWorkflowStepUpdate(RequestModel $request, string $stepName, string $action, string $performerName, string $notes = null): void
    {
        $employee = $request->employee;

        // Prepare message based on action
        $message = $this->getWorkflowStepMessage($stepName, $action, $performerName, $notes);
        $title = $this->getWorkflowStepTitle($action);

        // Send in-app notification
        $this->sendInAppNotification(
            $request,
            $employee->id,
            $title,
            $message
        );

        // Get current workflow status and next step info
        $workflowInfo = $this->getWorkflowInfoForEmail($request, $stepName, $action);

        // Send email notification with enhanced details
        try {
            Mail::to($employee->email)->send(new EmployeeNotificationMail($request, $employee, 'workflow_update', $message, $workflowInfo));
        } catch (\Exception $e) {
            Log::error('Failed to send workflow step update email: ' . $e->getMessage());
        }
    }

    /**
     * Get workflow information for email
     */
    private function getWorkflowInfoForEmail(RequestModel $request, string $stepName, string $action): array
    {
        $workflowInfo = [
            'current_step' => $stepName,
            'action' => $action,
            'performer' => null,
            'notes' => null,
            'next_step' => null,
            'workflow_progress' => null,
            'completed_steps' => [],
            'pending_steps' => []
        ];

        // Get workflow steps for this request
        $steps = \App\Models\WorkflowStep::getStepsForRequest($request);

        if ($steps->isNotEmpty()) {
            $completedSteps = [];
            $pendingSteps = [];
            $currentStepIndex = -1;

            foreach ($steps as $index => $step) {
                if ($step->name === $stepName) {
                    $currentStepIndex = $index;
                }

                // Check if step is completed (simplified check)
                $isCompleted = $this->isStepCompleted($request, $step);

                if ($isCompleted) {
                    // Get completion time from audit log
                    $completionLog = \App\Models\AuditLog::where('request_id', $request->id)
                        ->whereIn('action', ['Step completed', 'Workflow Step Completed'])
                        ->where('notes', 'like', '%' . $step->name . '%')
                        ->orderBy('created_at', 'desc')
                        ->first();

                    $completedSteps[] = [
                        'name' => $step->name,
                        'description' => $step->description,
                        'completed_at' => $completionLog ? $completionLog->created_at->format('M d, Y H:i') : now()->format('M d, Y H:i')
                    ];
                } else {
                    $pendingSteps[] = [
                        'name' => $step->name,
                        'description' => $step->description,
                        'is_current' => $step->name === $stepName
                    ];
                }
            }

            $workflowInfo['completed_steps'] = $completedSteps;
            $workflowInfo['pending_steps'] = $pendingSteps;
            $workflowInfo['workflow_progress'] = [
                'completed' => count($completedSteps),
                'total' => $steps->count(),
                'percentage' => $steps->count() > 0 ? round((count($completedSteps) / $steps->count()) * 100, 1) : 0
            ];

            // Get next step
            if ($currentStepIndex >= 0 && $currentStepIndex < $steps->count() - 1) {
                $nextStep = $steps[$currentStepIndex + 1];
                $workflowInfo['next_step'] = [
                    'name' => $nextStep->name,
                    'description' => $nextStep->description
                ];
            }
        }

        // Get performer info from recent audit logs
        $recentLog = \App\Models\AuditLog::where('request_id', $request->id)
            ->whereIn('action', ['Workflow Step Completed', 'Step completed'])
            ->where('notes', 'like', '%' . $stepName . '%')
            ->orderBy('created_at', 'desc')
            ->first();

        if ($recentLog && $recentLog->user) {
            $workflowInfo['performer'] = [
                'name' => $recentLog->user->full_name,
                'role' => $recentLog->user->role ? $recentLog->user->role->name : 'Unknown',
                'department' => $recentLog->user->department ? $recentLog->user->department->name : 'Unknown'
            ];
            $workflowInfo['notes'] = $recentLog->notes;
        } elseif ($recentLog) {
            // If no user but we have a log, extract performer name from notes
            $workflowInfo['performer'] = [
                'name' => 'System',
                'role' => 'System',
                'department' => 'System'
            ];
            $workflowInfo['notes'] = $recentLog->notes;
        }

        return $workflowInfo;
    }

    /**
     * Check if step is completed (simplified version)
     */
    private function isStepCompleted(RequestModel $request, $step): bool
    {
        // Check if there's a completion log for this step
        return \App\Models\AuditLog::where('request_id', $request->id)
            ->whereIn('action', ['Step completed', 'Workflow Step Completed'])
            ->where('notes', 'like', '%' . $step->name . '%')
            ->exists();
    }

    /**
     * Get workflow step message
     */
    private function getWorkflowStepMessage(string $stepName, string $action, string $performerName, string $notes = null): string
    {
        if ($action === 'completed') {
            $baseMessage = "Step '{$stepName}' has been completed successfully";
        } else {
            $baseMessage = "Step '{$stepName}' has been {$action} by {$performerName}";
        }

        if ($notes) {
            $baseMessage .= ". Notes: {$notes}";
        }

        return $baseMessage;
    }

    /**
     * Get workflow step title
     */
    private function getWorkflowStepTitle(string $action): string
    {
        switch ($action) {
            case 'approved':
                return 'Step Approved';
            case 'rejected':
                return 'Step Rejected';
            case 'delayed':
                return 'Step Delayed';
            case 'verified':
                return 'Step Verified';
            case 'ordered':
                return 'Order Placed';
            case 'delivered':
                return 'Order Delivered';
            case 'cancelled':
                return 'Order Cancelled';
            case 'completed':
                return 'Step Completed';
            default:
                return 'Workflow Update';
        }
    }

    /**
     * Get employee message based on status
     */
    private function getEmployeeMessage(RequestModel $request, string $status, string $reason = null): string
    {
        return match($status) {
            'approved' => "Your request for {$request->item} has been approved and forwarded to procurement.",
            'rejected' => "Your request for {$request->item} has been rejected. Reason: {$reason}",
            'delivered' => "Your request for {$request->item} has been delivered successfully.",
            'procurement_verified' => "Your request for {$request->item} has been verified by procurement and sent for approval.",
            'procurement_rejected' => "Your request for {$request->item} has been rejected by procurement. Reason: {$reason}",
            default => "Your request for {$request->item} has been updated."
        };
    }

    /**
     * Get employee notification title based on status
     */
    private function getEmployeeTitle(string $status): string
    {
        return match($status) {
            'approved' => 'Request Approved',
            'rejected' => 'Request Rejected',
            'delivered' => 'Request Delivered',
            'procurement_verified' => 'Request Verified by Procurement',
            'procurement_rejected' => 'Request Rejected by Procurement',
            default => 'Request Update'
        };
    }

    /**
     * Mark notification as read
     */
    public function markAsRead(int $notificationId, int $userId): bool
    {
        $notification = Notification::where('id', $notificationId)
            ->where('receiver_id', $userId)
            ->first();

        if ($notification) {
            $notification->update(['status' => 'Read']);
            return true;
        }

        return false;
    }

    /**
     * Mark all notifications as read for a user
     */
    public function markAllAsRead(int $userId): int
    {
        return Notification::where('receiver_id', $userId)
            ->where('status', 'Unread')
            ->update(['status' => 'Read']);
    }

    /**
     * Get unread notification count for a user
     */
    public function getUnreadCount(int $userId): int
    {
        return Notification::where('receiver_id', $userId)
            ->where('status', 'Unread')
            ->count();
    }

    /**
     * Get notifications for a user
     */
    public function getUserNotifications(int $userId, int $limit = 20)
    {
        return Notification::where('receiver_id', $userId)
            ->with(['request.employee'])
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }
}
