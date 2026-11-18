<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * A notice from izSuite to a business's owner (Doc 15 sections 15.5 and
 * 15.6): a change to the business's status, or a message an admin wrote.
 * By e-mail as well as in the app, since a suspended or closed business
 * cannot be opened to read it there.
 */
class BusinessNoticeMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /** @param  list<string>  $lines  each its own paragraph */
    public function __construct(
        public string $firstName,
        public string $title,
        public array $lines,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: setting('site_name', config('app.name')).' - '.$this->title);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.business-notice');
    }
}
