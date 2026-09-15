<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Carries the one-time code that activates a newly registered account.
 *
 * Not queued, for the same reason TestMail is not: this project has no worker
 * running, and a code that sits in a queue nobody drains is worse than a
 * registration that reports the transport failed.
 */
class VerifyEmailOtp extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $otp,
        public int $expiresInMinutes,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: setting('site_name', config('app.name')).' - '.__('Verify your email address'),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.verify-email-otp',
            with: [
                'otp'              => $this->otp,
                'expiresInMinutes' => $this->expiresInMinutes,
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
