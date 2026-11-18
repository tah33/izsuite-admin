<?php

namespace App\Services\Billing;

use App\Models\Billing\SubscriptionInvoice;
use App\Services\Documents\GotenbergClient;
use App\Services\Support\ImageService;

/**
 * A subscription invoice - or, once paid, its receipt - as a PDF (Doc 13
 * section 15), drawn from what the invoice kept when it was issued, under
 * izSuite's logo as it is now.
 */
class SubscriptionPdf
{
    public function __construct(
        private GotenbergClient $gotenberg,
        private ImageService $images,
    ) {}

    /** @return array{0: string, 1: string} its file name and the PDF */
    public function render(SubscriptionInvoice $invoice, bool $receipt = false): array
    {
        $receipt = $receipt && $invoice->isPaid();

        $html    = view('billing.invoice', ['invoice' => $invoice, 'receipt' => $receipt, 'logo' => $this->logo()])->render();
        $footer  = view('documents.footer', ['template' => ['footer_text' => $invoice->seller['name'] ?? null], 'poweredBy' => false])->render();

        return [$receipt ? "Receipt-{$invoice->receipt_number}.pdf" : "Invoice-{$invoice->number}.pdf", $this->gotenberg->pdf($html, 'a4', $footer)];
    }

    /** The logo the admin uploaded (Settings > Branding), else izSuite's own - embedded, as Gotenberg fetches nothing. */
    private function logo(): ?string
    {
        $brand = public_path(config('brand.assets.logo', 'images/brand/izsuite-logo.svg'));

        return $this->images->dataUri(setting('site_logo'))
            ?? (is_file($brand) ? 'data:image/svg+xml;base64,'.base64_encode((string) file_get_contents($brand)) : null);
    }
}
