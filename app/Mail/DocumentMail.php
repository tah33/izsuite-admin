<?php

namespace App\Mail;

use Illuminate\Mail\Attachment;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * A business's email to its customer with a document's PDF (Doc 06 section
 * 15): from the platform's address under the business's sender name, with
 * replies to the business. The body is the business's own text, shown as it
 * was written - escaped, its lines kept and its web addresses made links.
 *
 * Sent from SendDocumentEmail's queue job, so not queued itself.
 */
class DocumentMail extends Mailable
{
    public function __construct(
        public string $mailSubject,
        public string $body,
        public string $senderName,
        public ?string $replyToAddress,
        public string $filename,
        public string $pdf,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address((string) config('mail.from.address'), $this->senderName),
            replyTo: $this->replyToAddress ? [new Address($this->replyToAddress, $this->senderName)] : [],
            subject: $this->mailSubject,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.document',
            text: 'emails.document-text',
            with: ['body' => $this->body, 'senderName' => $this->senderName],
        );
    }

    public function attachments(): array
    {
        return [Attachment::fromData(fn () => $this->pdf, $this->filename)->withMime('application/pdf')];
    }
}
