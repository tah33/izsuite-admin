<?php

namespace App\Mail;

use App\Models\User\StorefrontCustomerCode;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * A code for a storefront customer (Doc 19 Phase E1): to verify their e-mail,
 * or to reset their password - from izSuite's address under the store's
 * name. Sent at once, as izSuite's own codes are, so the code never waits
 * in the queue's storage.
 */
class StoreCustomerCodeMail extends Mailable
{
    public function __construct(
        public string $storeName,
        public ?string $replyToAddress,
        public string $purpose,
        public string $code,
        public int $minutes,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address((string) config('mail.from.address'), $this->storeName),
            replyTo: $this->replyToAddress ? [new Address($this->replyToAddress, $this->storeName)] : [],
            subject: ($this->purpose === StorefrontCustomerCode::RESET ? 'Your code to reset your password at ' : 'Your code to verify your e-mail at ').$this->storeName,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.store-customer-code',
            text: 'emails.store-customer-code-text',
            with: [
                'storeName' => $this->storeName,
                'reset'     => $this->purpose === StorefrontCustomerCode::RESET,
                'code'      => $this->code,
                'minutes'   => $this->minutes,
            ],
        );
    }
}
