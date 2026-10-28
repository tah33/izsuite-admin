<?php

namespace App\Services\Documents;

use App\Enums\DocumentType;
use App\Models\User\SalesDocument;
use App\Models\User\SalesPayment;
use App\Models\User\Workspace;
use App\Services\Api\Sales\SalesSnapshots;

/**
 * What a document email's placeholders stand for (Doc 11 section 22) -
 * {document_number}, {amount} and the rest of EmailSetting::PLACEHOLDERS -
 * written as the document's template writes dates and money.
 *
 * One not known yet is left out, so its placeholder stays as it is: a draft
 * quotation's number until sending numbers it, a link until one is made.
 */
class DocumentEmailText
{
    public function __construct(
        protected DocumentTemplates $documentTemplates,
        protected SalesSnapshots $salesSnapshots,
    ) {}

    /** @return array<string, string> placeholder => value */
    public function forDocument(Workspace $workspace, SalesDocument $document, ?string $link): array
    {
        $format   = DocumentFormat::of($this->documentTemplates->settingsFor($workspace, $document->type, $document->template_snapshot));
        $customer = $this->customer($document);

        return array_filter([
            'business_name'   => $this->businessName($workspace, $document),
            'contact_name'    => ($customer['contact_person'] ?? null) ?: ($customer['name'] ?? ''),
            'document_number' => $document->number,
            'document_date'   => $format->date($document->document_date),
            'due_date'        => $document->due_date ? $format->date($document->due_date) : '',
            'amount'          => $format->money((string) $document->grand_total, $document->currency),
            'balance'         => $format->money((string) $document->balance_due, $document->currency),
            'document_link'   => $link,
        ], fn (?string $value) => $value !== null);
    }

    /** A receipt has no link: its placeholder is left empty. Expects document and account loaded. */
    public function forReceipt(Workspace $workspace, SalesPayment $payment): array
    {
        $invoice  = $payment->document;
        $format   = DocumentFormat::of($this->documentTemplates->settingsFor($workspace, DocumentType::CustomerReceipt, $payment->template_snapshot));
        $customer = $this->customer($invoice);

        return [
            'business_name'   => $this->businessName($workspace, $invoice),
            'contact_name'    => ($customer['contact_person'] ?? null) ?: ($customer['name'] ?? ''),
            'document_number' => (string) $payment->number,
            'document_date'   => $format->date($payment->payment_date),
            'due_date'        => $invoice->due_date ? $format->date($invoice->due_date) : '',
            'amount'          => $format->money((string) $payment->amount, $payment->account->currency),
            'balance'         => $format->money((string) $payment->balance_after, $invoice->currency),
            'document_link'   => '',
        ];
    }

    /** $text with each placeholder in $values filled in. */
    public function fill(string $text, array $values): string
    {
        return strtr($text, collect($values)->mapWithKeys(fn (string $value, string $key) => ['{'.$key.'}' => $value])->all());
    }

    /** The customer as the document kept them, or - a draft - as they are now. */
    public function customer(SalesDocument $document): array
    {
        return $document->customer_snapshot ?? ($document->contact ? $this->salesSnapshots->customer($document->contact) : []);
    }

    private function businessName(Workspace $workspace, SalesDocument $document): string
    {
        return ($document->business_snapshot ?? $this->salesSnapshots->business($workspace, $document->location))['name'];
    }
}
