<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * An abandoned cart's recovery e-mail to its customer (Doc 19 section 19.32 -
 * Phase H3b): from the platform's address under the store's name, with
 * replies to the store's support e-mail. Its cart comes ready to show - its
 * products and their amounts as last priced, its total, the link back to it
 * and, for a signed-in customer, their account's (EcommerceCartRecovery).
 *
 * Sent from SendStoreCartRecoveryEmail's queue job, so not queued itself.
 */
class StoreCartRecoveryMail extends Mailable
{
    public function __construct(
        public string $mailSubject,
        public string $storeName,
        public ?string $replyToAddress,
        public array $cart,
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
            view: 'emails.store-cart-recovery',
            text: 'emails.store-cart-recovery-text',
            with: ['storeName' => $this->storeName, 'cart' => $this->cart],
        );
    }
}
