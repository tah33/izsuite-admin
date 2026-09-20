<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Carries the one-time code that opens a password reset.
 *
 * Not queued, for the same reason TestMail and VerifyEmailOtp are not: this
 * project runs no worker, and a code parked in a queue nobody drains is worse
 * than a request that reports the transport failed.
 */
class PasswordResetOtp extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $otp,
        public int $expiresInMinutes,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: setting('site_name', config('app.name')).' - '.__('Reset your password'),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.password-reset-otp',
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
