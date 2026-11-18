<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;

/**
 * Tells an admin that their account signed in to the admin panel from a
 * browser it had not used before (Doc 15 section 15.2), so a sign-in that
 * was not theirs does not go unnoticed. Queued, so a slow mail server never
 * holds up the sign-in.
 */
class AdminNewSignIn extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $firstName,
        public Carbon $signedInAt,
        public ?string $ipAddress,
        public string $browser,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: setting('site_name', config('app.name')).' - '.__('New sign-in to the admin panel'),
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.admin-new-sign-in');
    }
}
