<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * A notice from izSuite support about a user's own account (Doc 15 section
 * 15.7): a link to choose a new password, the account locked or unlocked,
 * or its two-factor sign-in reset. A button when there is a link to follow.
 */
class AccountNoticeMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /** @param  list<string>  $lines  each its own paragraph */
    public function __construct(
        public string $firstName,
        public string $title,
        public array $lines,
        public ?string $actionText = null,
        public ?string $actionUrl = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: setting('site_name', config('app.name')).' - '.$this->title);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.account-notice');
    }
}
