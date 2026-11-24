<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * An online order's e-mail to its customer (Doc 19 section 19.35 - Phase
 * D5): from the platform's address under the store's name, with replies to
 * the store's support e-mail. Its order comes ready to show (StoreOrderMailer).
 *
 * Sent from SendStoreOrderEmail's queue job, so not queued itself.
 */
class StoreOrderMail extends Mailable
{
    public function __construct(
        public string $mailSubject,
        public string $storeName,
        public ?string $replyToAddress,
        public ?string $supportPhone,
        public array $order,
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
            view: 'emails.store-order',
            text: 'emails.store-order-text',
            with: ['storeName' => $this->storeName, 'supportPhone' => $this->supportPhone, 'order' => $this->order],
        );
    }
}
