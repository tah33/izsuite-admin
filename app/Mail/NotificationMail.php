<?php

namespace App\Mail;

use App\Models\Shared\UserNotification;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * A notification by e-mail (Doc 14 section 15 - Phase B1): its title, its
 * words, and a link to the page that shows it. Sent from the queued
 * DeliverNotificationEmail, which keeps how it went - so not queued itself.
 */
class NotificationMail extends Mailable
{
    public function __construct(
        public UserNotification $notification,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->notification->title);
    }

    public function content(): Content
    {
        $link = $this->notification->link;

        return new Content(
            view: 'emails.notification',
            with: [
                'title'    => $this->notification->title,
                'body'     => $this->notification->body,
                'business' => $this->notification->workspace?->name,
                'link'     => rtrim((string) config('app.frontend_url'), '/').($link ?: '/dashboard'),
            ],
        );
    }
}
