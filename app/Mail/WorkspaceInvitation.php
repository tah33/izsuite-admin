<?php

namespace App\Mail;

use App\Mail\Concerns\UsesEmailTemplate;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * An invitation to join a business (Doc 12 section 6): who sent it, the
 * role it comes with, and the secure link that accepts it - until it expires.
 *
 * Sent as the invitation is made, not queued: the business sees at once
 * whether it went, and sends it again if not.
 */
class WorkspaceInvitation extends Mailable
{
    use Queueable, SerializesModels, UsesEmailTemplate;

    protected function templateKey(): string
    {
        return 'account.invitation';
    }

    protected function templateVariables(): array
    {
        return ['invitee' => $this->inviteeName, 'business' => $this->businessName, 'inviter' => $this->inviterName, 'role' => $this->roleName, 'days' => $this->days, 'link' => $this->link];
    }

    public function __construct(
        public string $inviteeName,
        public string $businessName,
        public string $inviterName,
        public string $roleName,
        public string $link,
        public int $days,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->templated()['subject'] ?? "{$this->inviterName} invited you to {$this->businessName} on ".setting('site_name', config('app.name')),
        );
    }

    /** In the admin's words when it wrote them (Doc 15 Phase F2), else these. */
    public function content(): Content
    {
        if ($words = $this->templated()) {
            return $this->templatedContent($words);
        }

        return new Content(view: 'emails.workspace-invitation');
    }

    public function attachments(): array
    {
        return [];
    }
}
