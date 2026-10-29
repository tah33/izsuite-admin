<?php

namespace App\Services\Documents;

use App\Enums\DocumentType;
use App\Enums\SalesDocumentStatus;
use App\Models\User\SalesDocument;
use App\Models\User\SalesPayment;
use App\Models\User\Workspace;
use App\Repositories\User\WorkspaceFileRepository;
use App\Services\Api\Sales\SalesSnapshots;
use App\Services\Files\FileStorage;
use App\Support\Decimal;

/**
 * The shared document-rendering service (Doc 14 section 14.13): a sales
 * document or a payment receipt drawn up as HTML - for its PDF, its print and
 * its secure link alike - in its template's colour, font, layout and formats.
 *
 * A numbered document is drawn from what it kept: the business, the customer
 * and the template as they were (Doc 06 section 15). A draft shows them as
 * they are now. The logo and a signature image travel inside the HTML. What
 * is only the business's own - internal notes, account balances - never
 * appears. "Powered by izSuite" always does (Billing Starter).
 */
class DocumentRenderer
{
    public function __construct(
        protected DocumentTemplates $documentTemplates,
        protected DocumentSheet $documentSheet,
        protected SalesSnapshots $salesSnapshots,
        protected WorkspaceFileRepository $workspaceFileRepository,
        protected FileStorage $fileStorage,
    ) {}

    /**
     * $pdfUrl draws it for a screen, as its secure link shows it, with a link
     * to download its PDF. A template preview's sample carries the settings
     * being tried as its snapshot.
     */
    public function sales(Workspace $workspace, SalesDocument $document, ?string $pdfUrl = null): RenderedDocument
    {
        $template = $this->documentTemplates->settingsFor($workspace, $document->type, $document->template_snapshot);
        $format   = DocumentFormat::of($template);
        $business = $document->business_snapshot ?? $this->salesSnapshots->business($workspace, $document->location, $document->type->isPaidFrom() ? $document->currency : null);
        $customer = $document->customer_snapshot ?? ($document->contact ? $this->salesSnapshots->customer($document->contact) : null);
        $label    = $document->type->label();

        $data = [
            'title'     => $document->number ? "{$label} {$document->number}" : "Draft {$label}",
            'label'     => $label,
            'number'    => $document->number,
            'stamp'     => $this->stamp($document),
            'template'  => $template,
            'business'  => $business,
            'logo'      => $this->image($workspace, $template['show_logo'] ? ($business['logo_file_id'] ?? null) : null),
            'signature' => $this->image($workspace, $template['signature_file_id'] ?? null),
            'customer'  => $customer,
            'partyLabel' => match ($document->type) {
                DocumentType::Invoice, DocumentType::Proforma => 'Bill To',
                DocumentType::CreditNote                       => 'Credit To',
                default                                        => 'Prepared For',
            },
            'facts'     => $this->documentSheet->facts($document, $format, $template, $workspace->base_currency),
            'columns'   => $this->documentSheet->columns($document, $template),
            'lines'     => $this->documentSheet->lines($document, $format, $template),
            'totals'    => $this->documentSheet->totals($document, $format, $workspace->base_currency),
            'texts'     => $this->documentSheet->texts($document, $template, $business['payment_accounts'] ?? []),
            'proforma'  => $document->type === DocumentType::Proforma,
            'lang'      => $template['language'],
            'pdfUrl'    => $pdfUrl,
        ];

        return new RenderedDocument(
            view('documents.sales', $data)->render(),
            view('documents.footer', ['template' => $template])->render(),
            $template['paper_size'],
            $this->filename($label, $document->number, $document->id),
        );
    }

    public function receipt(Workspace $workspace, SalesPayment $payment): RenderedDocument
    {
        $template = $this->documentTemplates->settingsFor($workspace, DocumentType::CustomerReceipt, $payment->template_snapshot);
        $format   = DocumentFormat::of($template);
        $document = $payment->document;
        $business = $document->business_snapshot ?? $this->salesSnapshots->business($workspace, $document->location);

        $data = [
            'title'     => "Payment Receipt {$payment->number}",
            'number'    => $payment->number,
            'stamp'     => $payment->isCompleted() ? null : 'Reversed',
            'template'  => $template,
            'business'  => $business,
            'logo'      => $this->image($workspace, $template['show_logo'] ? ($business['logo_file_id'] ?? null) : null),
            'signature' => $this->image($workspace, $template['signature_file_id'] ?? null),
            'customer'  => $document->customer_snapshot ?? ($payment->contact ? $this->salesSnapshots->customer($payment->contact) : null),
            'rows'      => $this->documentSheet->receiptRows($payment, $format),
            'amount'    => $format->money((string) $payment->amount, $payment->account->currency),
            'lang'      => $template['language'],
            'pdfUrl'    => null,
        ];

        return new RenderedDocument(
            view('documents.receipt', $data)->render(),
            view('documents.footer', ['template' => $template])->render(),
            $template['paper_size'],
            $this->filename('Receipt', $payment->number, $payment->id),
        );
    }

    /** DRAFT, VOID, CANCELLED - or PAID for an invoice settled in full. */
    private function stamp(SalesDocument $document): ?string
    {
        return match (true) {
            $document->status === SalesDocumentStatus::Draft     => 'Draft',
            $document->status === SalesDocumentStatus::Void      => 'Void',
            $document->status === SalesDocumentStatus::Cancelled => 'Cancelled',
            $document->type === DocumentType::Invoice && in_array($document->payment_status, ['paid', 'refunded'], true)
                && Decimal::isZero((string) $document->balance_due) => 'Paid',
            default                                               => null,
        };
    }

    /** A business file - the logo, a signature - as a data: address, or null when it is not there. */
    private function image(Workspace $workspace, ?int $fileId): ?string
    {
        $file = $fileId ? $this->workspaceFileRepository->findFor($workspace, $fileId) : null;

        return $file ? $this->fileStorage->dataUri($file) : null;
    }

    /**
     * "Invoice-INV-00001.pdf", or "Invoice-draft-12.pdf" before it is
     * numbered. A number's slashes - INV/2026/1 - become dashes in a file name.
     */
    private function filename(string $label, ?string $number, int $id): string
    {
        return str_replace([' ', '/', '\\'], '-', $label.'-'.($number ?? "draft-{$id}")).'.pdf';
    }
}
