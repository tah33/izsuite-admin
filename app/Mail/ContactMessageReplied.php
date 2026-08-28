<?php

namespace App\Mail;

use App\Models\Admin\ContactMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ContactMessageReplied extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public ContactMessage $contactMessage,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->contactMessage->reply_subject ?? ('Re: '.$this->contactMessage->subject),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.contact-messages.replied',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
