<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * A request for a quotation's e-mail to its customer, saying it arrived (Doc
 * 19 section 19.30 - Phase H4a): from the platform's address under the
 * store's name, with replies to the store's support e-mail. Its request
 * comes ready to show - its number, products, delivery, the date wanted and
 * the message (StorefrontQuoteRequests).
 *
 * Sent from SendStoreQuoteRequestEmail's queue job, so not queued itself.
 */
class StoreQuoteRequestMail extends Mailable
{
    public function __construct(
        public string $mailSubject,
        public string $storeName,
        public ?string $replyToAddress,
        public array $quote,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address((string) config('mail.from.address'), $this->storeName),
            replyTo: $this->replyToAddress ? [new Address($this->replyToAddress, $this->storeName)] : [],
            subject: $this->mailSubject,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.store-quote-request',
            text: 'emails.store-quote-request-text',
            with: ['storeName' => $this->storeName, 'quote' => $this->quote],
        );
    }
}
