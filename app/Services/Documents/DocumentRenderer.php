<?php

namespace App\Services\Documents;

use App\Enums\DocumentType;
use App\Enums\ExpenseStatus;
use App\Enums\PurchaseStatus;
use App\Enums\SalesDocumentStatus;
use App\Models\User\Expense;
use App\Models\User\ExpensePayment;
use App\Models\User\Purchase;
use App\Models\User\SalesDocument;
use App\Models\User\SalesPayment;
use App\Models\User\SupplierPayment;
use App\Models\User\Workspace;
use App\Repositories\User\WorkspaceFileRepository;
use App\Services\Api\Sales\SalesSnapshots;
use App\Services\Files\FileStorage;
use App\Support\Decimal;

/**
 * The shared document-rendering service (Doc 14 section 14.13): a sales
 * document, a payment receipt, a purchase's or an expense's record or a
 * supplier or expense payment voucher drawn up as HTML - for its PDF, its print and its
 * secure link alike - in its template's colour, font, layout and formats.
 *
 * A numbered document is drawn from what it kept: the business, the customer,
 * supplier or payee and the template as they were (Doc 06 section 15). A draft
 * shows them as they are now. The logo and a signature image travel inside the HTML. What
 * is only the business's own - internal notes, account balances - never
 * appears. "Powered by izSuite" always does (Billing Starter).
 */
class DocumentRenderer
{
    public function __construct(
        protected DocumentTemplates $documentTemplates,
        protected DocumentSheet $documentSheet,
        protected PurchaseSheet $purchaseSheet,
        protected ExpenseSheet $expenseSheet,
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
        $customer = $document->customer_snapshot ?? ($document->contact ? $this->salesSnapshots->customerOf($document) : null);
        $label    = $document->type->label();
        // Where it goes, once the document says how or when (Doc 16 Phase A): its own address, else the customer's.
        $shipping = $document->shipping_address || $document->shipping_method || $document->shipping_terms || $document->expected_delivery_date;

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
            'shipTo'    => $shipping && $customer ? ($customer['shipping_address'] ?? $customer['billing_address'] ?? null) : null,
            'partyLabel' => match ($document->type) {
                DocumentType::Invoice, DocumentType::Proforma, DocumentType::SalesOrder => 'Bill To',
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
            $this->footer($template, $business),
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
            'heading'        => 'Payment Receipt',
            'partyLabel'     => 'Received From',
            'amountLabel'    => 'Amount Received',
            'signatureLabel' => 'Received By',
            'lang'      => $template['language'],
            'pdfUrl'    => null,
        ];

        return new RenderedDocument(
            view('documents.receipt', $data)->render(),
            $this->footer($template, $business),
            $template['paper_size'],
            $this->filename('Receipt', $payment->number, $payment->id),
        );
    }

    /** A purchase's record (Doc 09 section 10): its supplier, their invoice, its lines and what it still owes. */
    public function purchase(Workspace $workspace, Purchase $purchase): RenderedDocument
    {
        $template = $this->documentTemplates->settingsFor($workspace, DocumentType::Purchase, $purchase->template_snapshot);
        $format   = DocumentFormat::of($template);
        $business = $purchase->business_snapshot ?? $this->salesSnapshots->business($workspace, $purchase->location);

        $data = [
            'title'      => $purchase->number ? "Purchase {$purchase->number}" : 'Draft Purchase',
            'label'      => 'Purchase',
            'number'     => $purchase->number,
            'stamp'      => match (true) {
                $purchase->status === PurchaseStatus::Draft                                         => 'Draft',
                $purchase->status === PurchaseStatus::Void                                          => 'Void',
                $purchase->payment_status === Purchase::PAYMENT_PAID && Decimal::isZero((string) $purchase->balance_due) => 'Paid',
                default                                                                             => null,
            },
            'template'   => $template,
            'business'   => $business,
            'logo'       => $this->image($workspace, $template['show_logo'] ? ($business['logo_file_id'] ?? null) : null),
            'signature'  => $this->image($workspace, $template['signature_file_id'] ?? null),
            'customer'   => $purchase->supplier_snapshot ?? ($purchase->contact ? $this->salesSnapshots->customer($purchase->contact) : null),
            'partyLabel' => 'Supplier',
            'facts'      => $this->purchaseSheet->facts($purchase, $format, $template, $workspace->base_currency),
            'columns'    => $this->documentSheet->columns($purchase, $template),
            'lines'      => $this->documentSheet->lines($purchase, $format, $template),
            'totals'     => $this->purchaseSheet->totals($purchase, $format, $workspace->base_currency),
            'texts'      => $this->purchaseSheet->texts($purchase, $template),
            'lang'       => $template['language'],
        ];

        return new RenderedDocument(
            view('documents.record', $data)->render(),
            $this->footer($template, $business),
            $template['paper_size'],
            $this->filename('Purchase', $purchase->number, $purchase->id),
        );
    }

    /** An expense's record (Doc 10 section 10): its payee, the receipt, its lines and what it still owes. */
    public function expense(Workspace $workspace, Expense $expense): RenderedDocument
    {
        $template = $this->documentTemplates->settingsFor($workspace, DocumentType::Expense, $expense->template_snapshot);
        $format   = DocumentFormat::of($template);
        $business = $expense->business_snapshot ?? $this->salesSnapshots->business($workspace, $expense->location);

        $data = [
            'title'        => $expense->number ? "Expense {$expense->number}" : 'Draft Expense',
            'label'        => 'Expense',
            'number'       => $expense->number,
            'stamp'        => match (true) {
                $expense->status === ExpenseStatus::Draft                                         => 'Draft',
                $expense->status === ExpenseStatus::Void                                          => 'Void',
                $expense->payment_status === Expense::PAYMENT_PAID && Decimal::isZero((string) $expense->balance_due) => 'Paid',
                default                                                                           => null,
            },
            'template'     => $template,
            'business'     => $business,
            'logo'         => $this->image($workspace, $template['show_logo'] ? ($business['logo_file_id'] ?? null) : null),
            'signature'    => $this->image($workspace, $template['signature_file_id'] ?? null),
            'customer'     => $expense->payee_snapshot
                ?? ($expense->contact ? $this->salesSnapshots->customer($expense->contact) : ($expense->payee_name ? ['name' => $expense->payee_name] : null)),
            'partyLabel'   => 'Payee',
            'itemHeading'  => 'Category',
            'priceHeading' => 'Unit Amount',
            'facts'        => $this->expenseSheet->facts($expense, $format, $template, $workspace->base_currency),
            'columns'      => $this->expenseSheet->columns($expense, $template),
            'lines'        => $this->expenseSheet->lines($expense, $format, $template),
            'totals'       => $this->expenseSheet->totals($expense, $format, $workspace->base_currency),
            'texts'        => $this->expenseSheet->texts($expense, $template),
            'lang'         => $template['language'],
        ];

        return new RenderedDocument(
            view('documents.record', $data)->render(),
            $this->footer($template, $business),
            $template['paper_size'],
            $this->filename('Expense', $expense->number, $expense->id),
        );
    }

    /** A supplier payment's voucher (Doc 06 section 13): who was paid, how much, for which purchase. */
    public function supplierVoucher(Workspace $workspace, SupplierPayment $payment): RenderedDocument
    {
        $template = $this->documentTemplates->settingsFor($workspace, DocumentType::SupplierVoucher, $payment->template_snapshot);
        $format   = DocumentFormat::of($template);
        $purchase = $payment->purchase;
        $business = $purchase->business_snapshot ?? $this->salesSnapshots->business($workspace, $purchase->location);

        $data = [
            'title'          => "Supplier Payment Voucher {$payment->number}",
            'number'         => $payment->number,
            'stamp'          => $payment->isCompleted() ? null : 'Reversed',
            'template'       => $template,
            'business'       => $business,
            'logo'           => $this->image($workspace, $template['show_logo'] ? ($business['logo_file_id'] ?? null) : null),
            'signature'      => $this->image($workspace, $template['signature_file_id'] ?? null),
            'customer'       => $purchase->supplier_snapshot ?? ($payment->contact ? $this->salesSnapshots->customer($payment->contact) : null),
            'rows'           => $this->purchaseSheet->voucherRows($payment, $format),
            'amount'         => $format->money((string) $payment->amount, $payment->account->currency),
            'heading'        => 'Payment Voucher',
            'partyLabel'     => 'Paid To',
            'amountLabel'    => 'Amount Paid',
            'signatureLabel' => 'Authorised By',
            'lang'           => $template['language'],
        ];

        return new RenderedDocument(
            view('documents.receipt', $data)->render(),
            $this->footer($template, $business),
            $template['paper_size'],
            $this->filename('Voucher', $payment->number, $payment->id),
        );
    }

    /** An expense payment's voucher (Doc 06 section 13): who was paid, how much, for which expense. */
    public function expenseVoucher(Workspace $workspace, ExpensePayment $payment): RenderedDocument
    {
        $template = $this->documentTemplates->settingsFor($workspace, DocumentType::ExpenseVoucher, $payment->template_snapshot);
        $format   = DocumentFormat::of($template);
        $expense  = $payment->expense;
        $business = $expense->business_snapshot ?? $this->salesSnapshots->business($workspace, $expense->location);

        $data = [
            'title'          => "Expense Payment Voucher {$payment->number}",
            'number'         => $payment->number,
            'stamp'          => $payment->isCompleted() ? null : 'Reversed',
            'template'       => $template,
            'business'       => $business,
            'logo'           => $this->image($workspace, $template['show_logo'] ? ($business['logo_file_id'] ?? null) : null),
            'signature'      => $this->image($workspace, $template['signature_file_id'] ?? null),
            'customer'       => $expense->payee_snapshot
                ?? ($payment->contact ? $this->salesSnapshots->customer($payment->contact) : ($expense->payee_name ? ['name' => $expense->payee_name] : null)),
            'rows'           => $this->expenseSheet->voucherRows($payment, $format),
            'amount'         => $format->money((string) $payment->amount, $payment->account->currency),
            'heading'        => 'Payment Voucher',
            'partyLabel'     => 'Paid To',
            'amountLabel'    => 'Amount Paid',
            'signatureLabel' => 'Authorised By',
            'lang'           => $template['language'],
        ];

        return new RenderedDocument(
            view('documents.receipt', $data)->render(),
            $this->footer($template, $business),
            $template['paper_size'],
            $this->filename('Voucher', $payment->number, $payment->id),
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

    /**
     * The foot of each page, "Powered by izSuite" included as the document
     * was issued with it (Doc 13 section 18): its business snapshot says so.
     * One issued before plans could take it off has no word, so it keeps it;
     * a draft follows the plan the business is on now.
     */
    private function footer(array $template, array $business): string
    {
        return view('documents.footer', ['template' => $template, 'poweredBy' => $business['powered_by'] ?? true])->render();
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
