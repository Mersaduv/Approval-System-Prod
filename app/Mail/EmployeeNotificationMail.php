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

class EmployeeNotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public $request;
    public $employee;
    public $status;
    public $reason;

    /**
     * Create a new message instance.
     */
    public function __construct(RequestModel $request, User $employee, string $status, string $reason = null)
    {
        $this->request = $request;
        $this->employee = $employee;
        $this->status = $status;
        $this->reason = $reason;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $subject = match($this->status) {
            'approved' => 'Request Approved - Request #' . $this->request->id,
            'rejected' => 'Request Rejected - Request #' . $this->request->id,
            'delivered' => 'Request Delivered - Request #' . $this->request->id,
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
            view: 'emails.employee-notification',
            with: [
                'request' => $this->request,
                'employee' => $this->employee,
                'status' => $this->status,
                'reason' => $this->reason,
                'statusMessage' => $this->getStatusMessage(),
                'actionRequired' => $this->getActionRequired(),
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
     * Get the status message based on the status
     */
    private function getStatusMessage(): string
    {
        return match($this->status) {
            'approved' => 'Your request has been approved and forwarded to the procurement team.',
            'rejected' => 'Your request has been rejected.',
            'delivered' => 'Your request has been delivered successfully.',
            default => 'Your request has been updated.'
        };
    }

    /**
     * Get action required message
     */
    private function getActionRequired(): string
    {
        return match($this->status) {
            'approved' => 'The procurement team will process your request and notify you when it\'s ready.',
            'rejected' => 'You may submit a new request with the necessary corrections.',
            'delivered' => 'Please confirm receipt of your requested item.',
            default => 'Please check the system for more details.'
        };
    }
}
