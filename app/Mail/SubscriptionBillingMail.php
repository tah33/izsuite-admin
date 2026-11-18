<?php

namespace App\Mail;

use App\Models\Billing\SubscriptionInvoice;
use App\Services\Billing\SubscriptionPdf;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Attachment;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * izSuite's invoice for a business's subscription - or its receipt once the
 * payment is confirmed (Doc 13 sections 15 and 19) - with the PDF attached.
 * Queued, and only once the transaction that issued it has committed; the
 * PDF is drawn when it is sent.
 */
class SubscriptionBillingMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public SubscriptionInvoice $invoice,
        public bool $receipt = false,
    ) {
        $this->afterCommit();
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->receipt
            ? "Receipt {$this->invoice->receipt_number}: payment received for {$this->invoice->package_name}"
            : "Invoice {$this->invoice->number} for your {$this->invoice->package_name} subscription");
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.subscription-billing',
            with: [
                'invoice' => $this->invoice,
                'receipt' => $this->receipt,
                'link'    => rtrim((string) config('app.frontend_url'), '/').'/settings?tab=subscription',
            ],
        );
    }

    public function attachments(): array
    {
        [$name, $pdf] = app(SubscriptionPdf::class)->render($this->invoice, $this->receipt);

        return [Attachment::fromData(fn () => $pdf, $name)->withMime('application/pdf')];
    }
}
