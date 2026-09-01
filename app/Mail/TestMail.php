<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Sent from Admin → Settings → Mail so the admin can confirm the saved SMTP
 * credentials actually work. Deliberately NOT queued: the admin needs the
 * transport error surfaced on the spot, not swallowed by a worker.
 */
class TestMail extends Mailable
{
    use Queueable, SerializesModels;

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: setting('site_name', config('app.name')).' - '.__('Test Email'),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.test-mail',
            with: [
                'siteName' => setting('site_name', config('app.name')),
                'host'     => setting('smtp_host'),
                'port'     => setting('smtp_port'),
                'sentAt'   => now()->toDayDateTimeString(),
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
