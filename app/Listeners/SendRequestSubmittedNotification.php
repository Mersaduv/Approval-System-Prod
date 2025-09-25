<?php

namespace App\Listeners;

use App\Events\RequestSubmitted;
use App\Services\NotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class SendRequestSubmittedNotification implements ShouldQueue
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
    public function handle(RequestSubmitted $event): void
    {
        // Only send notification to employee (no email)
        $this->notificationService->sendEmployeeNotification(
            $event->request,
            'submitted',
            'Your request has been submitted and is under review'
        );

        // DO NOT send procurement verification request here
        // This will be handled by the workflow step execution
    }
}
