<?php

namespace App\Mail;

use Illuminate\Mail\Attachment;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * A business's e-mail from one of its CRM records (Doc 17 Phase F3a): from the
 * platform's address under the business's sender name, with replies to the
 * business, its own text shown as a document e-mail's is, and what it
 * carries - sales documents' PDFs and files.
 *
 * Sent from SendCrmEmail's queue job, so not queued itself.
 */
class RecordMail extends Mailable
{
    /** @param  list<array{name: string, data: string, mime: string}>  $files */
    public function __construct(
        public string $mailSubject,
        public string $body,
        public string $senderName,
        public ?string $replyToAddress,
        public array $files,
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
        return array_map(fn (array $file) => Attachment::fromData(fn () => $file['data'], $file['name'])->withMime($file['mime']), $this->files);
    }
}
