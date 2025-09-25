<?php

namespace App\Listeners;

use App\Events\WorkflowStepExecuted;
use App\Services\NotificationService;
use App\Models\ApprovalToken;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

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

            // Send approval request email
            $this->notificationService->sendApprovalRequest(
                $request,
                $user,
                "Approval required for step: {$workflowStep->name}"
            );
        }
    }
}
