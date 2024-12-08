<?php

namespace App\Mail\Shared;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Attachment;

class EntityRemainderEmail extends Mailable
{
    use Queueable, SerializesModels;

    public $cc = [];
    public $emailMeACopy = [];
    public $emailSubject;
    public $emailBody;
    public $recipient;
    public $fileUrl;
    public $additionalAttachments;

    /**
     * Create a new message instance.
     */
    public function __construct(
        $cc,
        $emailMeACopy,
        $emailSubject,
        $emailBody,
        $recipient,
        $fileUrl,
        $additionalAttachments
    ) {
        $this->cc = $cc;
        $this->emailMeACopy = $emailMeACopy;
        $this->emailSubject = $emailSubject;
        $this->emailBody = $emailBody;
        $this->recipient = $recipient;
        $this->fileUrl = $fileUrl;
        $this->additionalAttachments = $additionalAttachments;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            to: $this->recipient,
            subject: $this->emailSubject,
            cc: $this->cc ?? [],
            bcc: $this->emailMeACopy ? auth()->user()->email : []
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'mail.entity_remainder',
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
        $attachments = [];

        // Add the main file if provided
        if ($this->fileUrl) {
            $attachments[] = Attachment::fromUrl($this->fileUrl);
        }

        // Add additional attachments if provided
        if ($this->additionalAttachments) {
            $urls = explode('|', $this->additionalAttachments) ?? [];
            foreach ($urls as $url) {
                $attachments[] = Attachment::fromUrl(trim($url));
            }
        }
        // dd($attachments);

        return (array) $attachments;
    }
}
