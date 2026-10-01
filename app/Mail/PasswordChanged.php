<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;

/**
 * Sent the moment a password actually finishes changing - through a reset or
 * from the profile page's "Change Password" - so the account holder has a
 * paper trail even on the run where they were not the one who changed it.
 *
 * Not queued, for the same reason PasswordResetOtp is not: this project runs
 * no worker, and a notice parked in a queue nobody drains is worse than a
 * request that reports the transport failed.
 */
class PasswordChanged extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $firstName,
        public string $email,
        public Carbon $changedAt,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: setting('site_name', config('app.name')).' - '.__('Your password was changed'),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.password-changed',
            with: [
                'firstName' => $this->firstName,
                'email'     => $this->email,
                'changedAt' => $this->changedAt,
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
