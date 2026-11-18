<?php

namespace App\Mail;

use App\Mail\Concerns\UsesEmailTemplate;
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
    use Queueable, SerializesModels, UsesEmailTemplate;

    protected function templateKey(): string
    {
        return 'account.verify_email';
    }

    protected function templateVariables(): array
    {
        return ['code' => $this->otp, 'minutes' => $this->expiresInMinutes];
    }

    public function __construct(
        public string $otp,
        public int $expiresInMinutes,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->templated()['subject'] ?? setting('site_name', config('app.name')).' - '.__('Verify your email address'),
        );
    }

    /** In the admin's words when it wrote them (Doc 15 Phase F2), else these. */
    public function content(): Content
    {
        if ($words = $this->templated()) {
            return $this->templatedContent($words);
        }

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
