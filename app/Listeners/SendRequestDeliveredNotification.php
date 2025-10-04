<?php

namespace App\Listeners;

use App\Events\RequestDelivered;
use App\Services\NotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class SendRequestDeliveredNotification implements ShouldQueue
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
    public function handle(RequestDelivered $event): void
    {
        // Send notification to employee
        $this->notificationService->sendEmployeeNotification(
            $event->request,
            'delivered',
            'Your request has been delivered successfully'
        );
    }
}
