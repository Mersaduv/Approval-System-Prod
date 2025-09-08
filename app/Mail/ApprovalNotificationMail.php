<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use App\Models\Request as RequestModel;
use App\Models\User;
use App\Models\ApprovalToken;

class ApprovalNotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public $request;
    public $approver;
    public $approvalToken;
    public $actionType;

    /**
     * Create a new message instance.
     */
    public function __construct(RequestModel $request, User $approver, ApprovalToken $approvalToken, string $actionType = 'approve')
    {
        $this->request = $request;
        $this->approver = $approver;
        $this->approvalToken = $approvalToken;
        $this->actionType = $actionType;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $subject = match($this->actionType) {
            'approve' => 'Approval Required - Request #' . $this->request->id,
            'reject' => 'Request Rejected - Request #' . $this->request->id,
            'forward' => 'Request Forwarded - Request #' . $this->request->id,
            default => 'Request Update - Request #' . $this->request->id
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
            view: 'emails.approval-notification',
            with: [
                'request' => $this->request,
                'approver' => $this->approver,
                'approvalToken' => $this->approvalToken,
                'actionType' => $this->actionType,
                'approvalUrl' => $this->getApprovalUrl(),
                'expiresAt' => $this->approvalToken->expires_at->format('M d, Y H:i'),
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
     * Get the approval URL with secure token
     */
    private function getApprovalUrl(): string
    {
        return url("/approval/{$this->approvalToken->token}");
    }
}
