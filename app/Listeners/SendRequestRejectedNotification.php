<?php

namespace App\Listeners;

use App\Events\RequestRejected;
use App\Services\NotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class SendRequestRejectedNotification implements ShouldQueue
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
    public function handle(RequestRejected $event): void
    {
        // Send notification to employee
        $this->notificationService->sendEmployeeNotification(
            $event->request,
            'rejected',
            $event->reason
        );
    }
}
