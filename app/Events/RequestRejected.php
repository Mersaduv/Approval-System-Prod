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

class RequestRejected
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $request;
    public $rejector;
    public $reason;

    /**
     * Create a new event instance.
     */
    public function __construct(RequestModel $request, User $rejector, string $reason)
    {
        $this->request = $request;
        $this->rejector = $rejector;
        $this->reason = $reason;
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
