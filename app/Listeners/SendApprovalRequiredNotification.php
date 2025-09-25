<?php

namespace App\Listeners;

use App\Events\ApprovalRequired;
use App\Services\NotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class SendApprovalRequiredNotification implements ShouldQueue
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
    public function handle(ApprovalRequired $event): void
    {
        // Send approval request notification
        $this->notificationService->sendApprovalRequest(
            $event->request,
            $event->approver,
            "Approval required: {$event->stepName}"
        );
    }
}
