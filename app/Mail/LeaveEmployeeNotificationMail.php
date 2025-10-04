<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use App\Models\LeaveRequest;
use App\Models\User;

class LeaveEmployeeNotificationMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public $leaveRequest;
    public $employee;
    public $status;
    public $reason;
    public $workflowInfo;
    public $statusMessage;

    /**
     * Create a new message instance.
     */
    public function __construct(LeaveRequest $leaveRequest, User $employee, string $status, string $reason = null, array $workflowInfo = null)
    {
        $this->leaveRequest = $leaveRequest;
        $this->employee = $employee;
        $this->status = $status;
        $this->reason = $reason;
        $this->workflowInfo = $workflowInfo;
        $this->statusMessage = $this->getStatusMessage($status);
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $subject = match($this->status) {
            'approved' => 'Leave Request Approved - Request #' . $this->leaveRequest->id,
            'rejected' => 'Leave Request Rejected - Request #' . $this->leaveRequest->id,
            'cancelled' => 'Leave Request Cancelled - Request #' . $this->leaveRequest->id,
            'workflow_update' => 'Leave Request Workflow Update - Request #' . $this->leaveRequest->id,
            default => 'Leave Request Update - Request #' . $this->leaveRequest->id
        };

        return new Envelope(
            subject: $subject,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.leave-employee-notification',
            with: [
                'leaveRequest' => $this->leaveRequest,
                'employee' => $this->employee,
                'status' => $this->status,
                'reason' => $this->reason,
                'workflowInfo' => $this->workflowInfo,
                'statusMessage' => $this->statusMessage,
            ]
        );
    }

    /**
     * Get the attachments for the message.
     */
    public function attachments(): array
    {
        return [];
    }

    /**
     * Get status message
     */
    private function getStatusMessage(string $status): string
    {
        return match($status) {
            'approved' => 'Your leave request has been approved and is now active.',
            'rejected' => 'Your leave request has been rejected. Please review the reason below.',
            'cancelled' => 'Your leave request has been cancelled.',
            'workflow_update' => 'There has been an update to your leave request workflow.',
            default => 'Your leave request status has been updated.'
        };
    }
}
