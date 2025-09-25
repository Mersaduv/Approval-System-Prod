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
    private function sendInAppNotification(RequestModel $request, int $receiverId, string $title, string $message): void
    {
        $this->sendNotification($request, $receiverId, $title, $message, 'InApp');
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
