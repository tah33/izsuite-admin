<?php

namespace App\Services\Api\Sales;

use App\Enums\DocumentType;
use App\Enums\SalesDocumentStatus;
use App\Models\User\SalesDocument;
use App\Models\User\SalesDocumentLine;
use App\Models\User\User;
use App\Models\User\Workspace;
use App\Repositories\User\DocumentLinkRepository;
use App\Repositories\User\SalesDocumentRepository;
use App\Services\Documents\DocumentNumbers;
use App\Services\Documents\DocumentTemplates;
use App\Services\Sales\SalesDocumentRules;
use App\Services\Shared\ActivityLogService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Credit notes (Doc 08 section 15, Doc 06 section 12): made from an issued
 * invoice - all of it, or some of its lines and amounts - saved as a draft,
 * then issued: numbered (CN-00001), kept with the customer as they are then,
 * and settled against the invoice (InvoiceSettlement). An issued credit note
 * is never changed or deleted; it is voided, but only before anything was
 * refunded out of it.
 *
 * The invoice itself is never changed: its sale is reduced by the credit
 * note, on the credit note's date.
 */
class CreditNoteService
{
    use RefusesConflicts;

    public function __construct(
        protected SalesDocumentRepository $salesDocumentRepository,
        protected SalesDocumentService $salesDocumentService,
        protected SalesDocumentRules $salesDocumentRules,
        protected CreditNoteLines $creditNoteLines,
        protected InvoiceSettlement $invoiceSettlement,
        protected SalesSnapshots $salesSnapshots,
        protected DocumentNumbers $documentNumbers,
        protected DocumentTemplates $documentTemplates,
        protected DocumentLinkRepository $documentLinkRepository,
    ) {}

    /**
     * Where the form starts: the invoice, the date - today, or the invoice's
     * own if that is later - and each invoice line with what is left to
     * credit of it.
     */
    public function defaults(Workspace $workspace, int $invoiceId): array
    {
        $invoice = $this->invoice($workspace, $invoiceId);
        $today   = $this->salesDocumentService->today($workspace);

        return [
            'invoice'       => $invoice,
            'creditable'    => $this->salesDocumentRules->creditable($invoice),
            'cash'          => bcsub((string) $invoice->amount_paid, (string) $invoice->refunded_amount, 2),
            'document_date' => max($today, $invoice->document_date),
            'lines'         => $this->creditNoteLines->remaining($invoice),
        ];
    }

    /** The totals the form shows as it is filled in - the same arithmetic as a save. */
    public function calculate(Workspace $workspace, int $invoiceId, array $lines): array
    {
        [$totals, $rows] = $this->creditNoteLines->build($this->invoice($workspace, $invoiceId), $lines);

        return [...$totals, 'lines' => array_map(fn (array $row) => array_intersect_key($row, ['discount_amount' => true, 'amount' => true, 'tax_amount' => true]), $rows)];
    }

    /** $data is validated (see SaveCreditNoteRequest). */
    public function create(Workspace $workspace, int $invoiceId, User $user, array $data): SalesDocument
    {
        $invoice = $this->invoice($workspace, $invoiceId);

        if (! $this->salesDocumentRules->allows($invoice, 'credit_note', $this->salesDocumentService->today($workspace))) {
            $this->refuse('This invoice has been credited in full already.');
        }

        [$totals, $rows] = $this->checked($invoice, $data);

        $note = DB::transaction(function () use ($workspace, $user, $data, $invoice, $totals, $rows) {
            $note = $this->salesDocumentRepository->create($workspace, [
                ...$this->header($invoice, $data),
                ...$totals,
                'type'       => DocumentType::CreditNote,
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ]);

            $this->salesDocumentRepository->replaceLines($note, $rows);

            return $note;
        });

        // Logged here, by what it is, so LogApiWriteActivity does not add its
        // own vaguer entry.
        ActivityLogService::record('created', "Added a draft credit note for invoice {$invoice->number}", $note, ['source' => 'api']);

        return $this->salesDocumentRepository->loadShown($note);
    }

    public function update(Workspace $workspace, int $id, User $user, array $data): SalesDocument
    {
        $note = $this->salesDocumentService->find($workspace, DocumentType::CreditNote, $id);

        if (! $this->salesDocumentRules->allows($note, 'edit', $this->salesDocumentService->today($workspace))) {
            $this->refuse('An issued credit note cannot be changed. Void it and make a new one instead.');
        }

        $invoice = $this->invoiceOf($workspace, $note);

        [$totals, $rows] = $this->checked($invoice, $data);

        DB::transaction(function () use ($user, $data, $note, $invoice, $totals, $rows) {
            $this->salesDocumentRepository->update($note, [...$this->header($invoice, $data), ...$totals, 'updated_by' => $user->id]);
            $this->salesDocumentRepository->replaceLines($note, $rows);
        });

        ActivityLogService::record('updated', "Updated the draft credit note for invoice {$invoice->number}", $note, ['source' => 'api']);

        return $this->salesDocumentRepository->loadShown($note);
    }

    /**
     * A draft becomes issued: checked once more against what was credited
     * since it was saved, numbered, its customer kept as they are, and set
     * against its invoice.
     */
    public function issue(Workspace $workspace, int $id, User $user): SalesDocument
    {
        $note = $this->salesDocumentService->find($workspace, DocumentType::CreditNote, $id);

        if (! $this->salesDocumentRules->allows($note, 'issue', $this->salesDocumentService->today($workspace))) {
            $this->refuse('Only a draft credit note can be issued.');
        }

        $invoice = $this->invoiceOf($workspace, $note);

        $this->creditNoteLines->check($invoice, $note->lines->map(fn (SalesDocumentLine $line) => $line->only(['source_line_id', 'quantity', 'unit_price', 'amount']))->all(), (string) $note->grand_total);

        DB::transaction(function () use ($workspace, $user, $note, $invoice) {
            $this->salesDocumentRepository->update($note, [
                'number'            => $this->documentNumbers->next(
                    $workspace,
                    DocumentType::CreditNote,
                    CarbonImmutable::parse($note->document_date),
                    fn (string $number) => $this->salesDocumentRepository->numberTaken($workspace, DocumentType::CreditNote, $number),
                ),
                'status'            => SalesDocumentStatus::Issued,
                'issued_at'         => now(),
                'business_snapshot' => $this->salesSnapshots->business($workspace, $note->location),
                'customer_snapshot' => $this->salesSnapshots->customer($note->contact),
                'template_snapshot' => $this->documentTemplates->snapshot($workspace, DocumentType::CreditNote),
                'updated_by'        => $user->id,
            ]);

            $this->invoiceSettlement->settle($invoice);
        });

        ActivityLogService::record('updated', "Issued credit note {$note->number} for invoice {$invoice->number}", $note, ['source' => 'api', 'status' => 'issued']);

        // Read again: the settlement wrote what it applied through its own copy.
        return $this->salesDocumentRepository->loadShown($note->refresh());
    }

    /** An issued credit note withdrawn: it stays on record, and its invoice owes again what it took off. */
    public function void(Workspace $workspace, int $id, User $user, ?string $reason): SalesDocument
    {
        $note = $this->salesDocumentService->find($workspace, DocumentType::CreditNote, $id);

        if (! $this->salesDocumentRules->allows($note, 'void', $this->salesDocumentService->today($workspace))) {
            $this->refuse($note->status === SalesDocumentStatus::Issued
                ? 'Money was refunded out of this credit note. Reverse those refunds before voiding it.'
                : 'Only an issued credit note can be voided. A draft is deleted instead.');
        }

        $invoice = $this->invoiceOf($workspace, $note);

        DB::transaction(function () use ($user, $reason, $note, $invoice) {
            $this->salesDocumentRepository->update($note, [
                'status'         => SalesDocumentStatus::Void,
                'status_reason'  => $reason,
                'payment_status' => SalesDocument::PAYMENT_NOT_APPLICABLE,
                'applied_amount' => '0',
                'balance_due'    => '0',
                'updated_by'     => $user->id,
            ]);

            $this->documentLinkRepository->revokeFor($note);
            $this->invoiceSettlement->settle($invoice);
        });

        ActivityLogService::record('updated', "Voided credit note {$note->number}", $note, ['source' => 'api', 'status' => 'void']);

        return $this->salesDocumentRepository->loadShown($note);
    }

    /** Its rows and totals, refused if they credit more than is left. */
    private function checked(SalesDocument $invoice, array $data): array
    {
        if ($data['document_date'] < $invoice->document_date) {
            throw ValidationException::withMessages(['document_date' => "A credit note cannot come before its invoice, dated {$invoice->document_date}."]);
        }

        [$totals, $rows] = $this->creditNoteLines->build($invoice, $data['lines']);

        $this->creditNoteLines->check($invoice, $rows, $totals['grand_total']);

        return [$totals, $rows];
    }

    /** What a credit note takes from its invoice, and what it says of itself. */
    private function header(SalesDocument $invoice, array $data): array
    {
        return [
            'contact_id'     => $invoice->contact_id,
            'location_id'    => $invoice->location_id,
            'document_date'  => $data['document_date'],
            'currency'       => $invoice->currency,
            'exchange_rate'  => (string) $invoice->exchange_rate,
            'price_tax_mode' => $invoice->price_tax_mode,
            'tax_rounding'   => $invoice->tax_rounding,
            'reason'         => $data['reason'],
            'notes'          => $data['notes'] ?? null,
            'source_id'      => $invoice->id,
        ];
    }

    /** An issued invoice of the business, with its lines - anything else answers like one that does not exist. */
    private function invoice(Workspace $workspace, int $id): SalesDocument
    {
        return $this->salesDocumentRepository->findIssuedInvoiceFor($workspace, $id)
            ?? abort(404, 'Issued invoice not found. A credit note is made from an issued invoice.');
    }

    private function invoiceOf(Workspace $workspace, SalesDocument $note): SalesDocument
    {
        return $this->salesDocumentRepository->findIssuedInvoiceFor($workspace, (int) $note->source_id)
            ?? $this->refuse('Its invoice is no longer issued, so this credit note can no longer be changed or issued.');
    }
}
