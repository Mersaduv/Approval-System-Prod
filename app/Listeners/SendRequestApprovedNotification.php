<?php

namespace App\Listeners;

use App\Events\RequestApproved;
use App\Services\NotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class SendRequestApprovedNotification implements ShouldQueue
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
    public function handle(RequestApproved $event): void
    {
        // Send notification to employee
        $this->notificationService->sendEmployeeNotification(
            $event->request,
            'approved',
            'Your request has been approved and forwarded to procurement'
        );

        // Send notification to procurement team
        $this->notificationService->sendProcurementNotification($event->request);
    }
}
