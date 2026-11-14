<?php

namespace App\Mail;

use App\Mail\Concerns\UsesEmailTemplate;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Sent the moment EmailVerificationService::verify() actually activates an
 * account - not on a resend, and not on a second submit of an already-spent
 * code, both of which leave the account exactly as verified as it already
 * was.
 *
 * Not queued, for the same reason VerifyEmailOtp is not: this project runs no
 * worker, and a welcome note parked in a queue nobody drains is worse than a
 * request that reports the transport failed.
 */
class EmailVerified extends Mailable
{
    use Queueable, SerializesModels, UsesEmailTemplate;

    protected function templateKey(): string
    {
        return 'account.email_verified';
    }

    protected function templateVariables(): array
    {
        return ['first_name' => $this->firstName, 'email' => $this->email, 'link' => rtrim(config('app.frontend_url'), '/').'/login'];
    }

    public function __construct(
        public string $firstName,
        public string $email,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->templated()['subject'] ?? setting('site_name', config('app.name')).' - '.__('Your account is active'),
        );
    }

    /** In the admin's words when it wrote them (Doc 15 Phase F2), else these. */
    public function content(): Content
    {
        if ($words = $this->templated()) {
            return $this->templatedContent($words);
        }

        return new Content(
            view: 'emails.email-verified',
            with: [
                'firstName' => $this->firstName,
                'email'     => $this->email,
                'loginUrl'  => rtrim(config('app.frontend_url'), '/').'/login',
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
