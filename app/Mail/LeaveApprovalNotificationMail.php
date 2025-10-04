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
use App\Models\ApprovalToken;

class LeaveApprovalNotificationMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public $leaveRequest;
    public $approver;
    public $approvalToken;
    public $actionType;
    public $stepName;

    /**
     * Create a new message instance.
     */
    public function __construct(LeaveRequest $leaveRequest, User $approver, ApprovalToken $approvalToken, string $actionType = 'approve', string $stepName = null)
    {
        $this->leaveRequest = $leaveRequest;
        $this->approver = $approver;
        $this->approvalToken = $approvalToken;
        $this->actionType = $actionType;
        $this->stepName = $stepName;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $subject = match($this->actionType) {
            'approve' => 'Leave Request Approval Required - Request #' . $this->leaveRequest->id,
            'reject' => 'Leave Request Rejected - Request #' . $this->leaveRequest->id,
            'forward' => 'Leave Request Forwarded - Request #' . $this->leaveRequest->id,
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
            view: 'emails.leave-approval-notification',
            with: [
                'leaveRequest' => $this->leaveRequest,
                'approver' => $this->approver,
                'approvalToken' => $this->approvalToken,
                'actionType' => $this->actionType,
                'stepName' => $this->stepName,
                'approvalUrl' => $this->getApprovalUrl(),
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
     * Get the approval URL
     */
    private function getApprovalUrl(): string
    {
        $baseUrl = config('app.url');
        $token = $this->approvalToken->token;
        return "{$baseUrl}/leave-requests/{$this->leaveRequest->id}?token={$token}";
    }
}
