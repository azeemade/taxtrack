<?php

namespace App\Mail\Shared;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class VendorRemainderEmail extends Mailable
{
    use Queueable, SerializesModels;

    private $vendorName;
    private $email;
    private $cc;
    private $emailMeACopy;
    private $emailSubject;
    private $emailBody;

    /**
     * Create a new message instance.
     */
    public function __construct($cc, $emailMeACopy, $emailSubject, $emailBody)
    {
        $this->cc = $cc;
        $this->emailMeACopy = $emailMeACopy;
        $this->emailSubject = $emailSubject;
        $this->emailBody = $emailBody;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->emailSubject,
            cc: $this->cc,
            bcc: $this->emailMeACopy ? auth()->user()->email : null
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'mail.vendor_remainder',
            with: [
                'emailBody' => $this->emailBody,
            ],
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
