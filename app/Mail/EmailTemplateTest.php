<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** A platform e-mail's template with its samples, sent to the admin testing it (Doc 15 section 15.20 - Phase F2). */
class EmailTemplateTest extends Mailable
{
    use Queueable, SerializesModels;

    /** @param array{subject: string, paragraphs: list<string>, button: ?string, link: ?string} $words */
    public function __construct(
        public array $words,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: '[Test] '.$this->words['subject']);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.template', with: ['words' => $this->words]);
    }
}
