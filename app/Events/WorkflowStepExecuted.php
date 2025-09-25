<?php

namespace App\Events;

use App\Models\Request as RequestModel;
use App\Models\WorkflowStep;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class WorkflowStepExecuted
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $request;
    public $workflowStep;
    public $assignedUsers;

    /**
     * Create a new event instance.
     */
    public function __construct(RequestModel $request, WorkflowStep $workflowStep, $assignedUsers)
    {
        $this->request = $request;
        $this->workflowStep = $workflowStep;
        $this->assignedUsers = $assignedUsers;
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
