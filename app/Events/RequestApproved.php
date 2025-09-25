<?php

namespace App\Events;

use App\Models\Request as RequestModel;
use App\Models\User;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RequestApproved
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $request;
    public $approver;
    public $notes;

    /**
     * Create a new event instance.
     */
    public function __construct(RequestModel $request, User $approver, string $notes = null)
    {
        $this->request = $request;
        $this->approver = $approver;
        $this->notes = $notes;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('channel-name'),
        ];
    }
}
