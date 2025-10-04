<?php

namespace App\Listeners;

use App\Events\WorkflowStepExecuted;
use App\Services\NotificationService;
use App\Models\ApprovalToken;
use App\Mail\ApprovalNotificationMail;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class SendWorkflowStepNotification implements ShouldQueue
{
    use InteractsWithQueue;

    protected $notificationService;

    /**
     * Create the event listener.
     */
    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     * Handle the event.
     */
    public function handle(WorkflowStepExecuted $event): void
    {
        $request = $event->request;
        $workflowStep = $event->workflowStep;
        $assignedUsers = $event->assignedUsers;

        // Send email to each assigned user
        foreach ($assignedUsers as $user) {
            // Create approval token for this user (never expires)
            $approvalToken = ApprovalToken::createToken(
                $request->id,
                $user->id,
                'approve'
            );

            // Send in-app notification
            $this->notificationService->sendInAppNotification(
                $request,
                $user->id,
                'Approval Required',
                "Approval required for step: {$workflowStep->name}"
            );

            // Send email notification directly using ApprovalNotificationMail
            try {
                Mail::to($user->email)->send(new ApprovalNotificationMail($request, $user, $approvalToken, 'approve'));
            } catch (\Exception $e) {
                Log::error('Failed to send workflow step email: ' . $e->getMessage());
            }
        }
    }
}
