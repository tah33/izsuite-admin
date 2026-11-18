<?php

namespace App\Services\Api\Sales;

use App\Enums\DocumentType;
use App\Enums\SalesDocumentStatus;
use App\Models\User\CustomerAdvance;
use App\Models\User\SalesAllocation;
use App\Models\User\SalesDocument;
use App\Models\User\User;
use App\Models\User\Workspace;
use App\Repositories\Shared\RowLockRepository;
use App\Repositories\User\CustomerAdvanceRepository;
use App\Repositories\User\SalesAllocationRepository;
use App\Services\Sales\SalesDocumentRules;
use App\Services\Shared\ActivityLogService;
use App\Support\Decimal;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Applying credit to a customer's issued invoices (Doc 16 sections 16.15 and
 * 16.18): part of an advance, or of a credit note's remaining credit, in the
 * invoice's currency, never more than either has left. A credit note's own
 * invoice takes its credit by itself (InvoiceSettlement), so it is never one
 * of the choices. Taking one off leaves it on record, removed with a reason.
 *
 * Each change settles the invoice again, and the credit note's own invoice
 * or the advance, in one transaction, with the invoice locked first.
 */
class SalesAllocationService
{
    use RefusesConflicts;

    public function __construct(
        protected SalesAllocationRepository $allocations,
        protected CustomerAdvanceRepository $advances,
        protected SalesDocumentService $salesDocumentService,
        protected CustomerAdvanceService $customerAdvanceService,
        protected InvoiceSettlement $invoiceSettlement,
        protected SalesDocumentRules $salesDocumentRules,
        protected RowLockRepository $rows,
    ) {}

    /** What an invoice can take: its customer's advances and other credit notes with credit left in its currency, oldest first. */
    public function sourcesFor(Workspace $workspace, int $invoiceId): Collection
    {
        $invoice = $this->openInvoice($workspace, $invoiceId);

        $advances = $this->advances->openFor($workspace, (int) $invoice->contact_id, $invoice->currency)
            ->map(fn (CustomerAdvance $advance) => ['kind' => 'advance', 'id' => $advance->id, 'number' => $advance->number, 'date' => $advance->payment_date, 'currency' => $advance->currency, 'available' => (string) $advance->balance]);

        $notes = $this->allocations->openCreditNotesFor($workspace, (int) $invoice->contact_id, $invoice->currency, $invoice->id)
            ->map(fn (SalesDocument $note) => ['kind' => 'credit_note', 'id' => $note->id, 'number' => $note->number, 'date' => $note->document_date, 'currency' => $note->currency, 'available' => (string) $note->balance_due]);

        return collect([...$advances, ...$notes]);
    }

    /** Where an advance can go: its customer's issued invoices still owing in its currency, oldest first. */
    public function invoicesForAdvance(Workspace $workspace, int $advanceId): Collection
    {
        $advance = $this->customerAdvanceService->find($workspace, $advanceId);

        return $this->targets($workspace, (int) $advance->contact_id, $advance->currency, null);
    }

    /** Where a credit note's credit can go: its customer's other issued invoices still owing in its currency. */
    public function invoicesForNote(Workspace $workspace, int $noteId): Collection
    {
        $note = $this->salesDocumentService->find($workspace, DocumentType::CreditNote, $noteId);

        return $this->targets($workspace, (int) $note->contact_id, $note->currency, $note->source_id);
    }

    /** $data is validated (see ApplyCreditRequest): advance_id or credit_note_id, and the amount. */
    public function apply(Workspace $workspace, User $user, int $invoiceId, array $data): SalesAllocation
    {
        $invoice = $this->openInvoice($workspace, $invoiceId);
        $advance = empty($data['advance_id']) ? null : $this->customerAdvanceService->find($workspace, (int) $data['advance_id']);
        $note    = $advance || empty($data['credit_note_id']) ? null : $this->salesDocumentService->find($workspace, DocumentType::CreditNote, (int) $data['credit_note_id']);
        $source  = $advance ?? $note ?? throw ValidationException::withMessages(['source' => 'Choose the advance or credit note to apply.']);
        $amount  = Decimal::round((string) $data['amount']);

        if ((int) $source->contact_id !== (int) $invoice->contact_id) {
            throw ValidationException::withMessages(['source' => 'Apply credit of the invoice\'s own customer.']);
        }

        if ($source->currency !== $invoice->currency) {
            throw ValidationException::withMessages(['source' => "This is in {$source->currency}; the invoice is in {$invoice->currency}."]);
        }

        if ($note && (int) $note->source_id === $invoice->id) {
            $this->refuse('A credit note applies to its own invoice by itself.');
        }

        $allocation = DB::transaction(function () use ($workspace, $user, $invoice, $advance, $note, $amount) {
            // What both still have, read again with them locked: another allocation or payment meanwhile counts.
            $invoice = $this->rows->lock($invoice);
            $source  = $this->rows->lock($advance ?? $note);
            $left    = $advance ? (string) $source->balance : (string) $source->balance_due;

            if ($advance ? ! $source->isCompleted() : $source->status !== SalesDocumentStatus::Issued) {
                $this->refuse($advance ? 'This advance was reversed.' : 'Only an issued credit note\'s credit is applied.');
            }

            if (bccomp($amount, (string) $invoice->balance_due, 2) > 0) {
                throw ValidationException::withMessages(['amount' => "The invoice has {$invoice->currency} {$invoice->balance_due} left to pay."]);
            }

            if (bccomp($amount, $left, 2) > 0) {
                throw ValidationException::withMessages(['amount' => "Only {$invoice->currency} {$left} of it is left to apply."]);
            }

            $allocation = $this->allocations->create($workspace, [
                'invoice_id'          => $invoice->id,
                'customer_advance_id' => $advance?->id,
                'credit_note_id'      => $note?->id,
                'amount'              => $amount,
                'allocation_date'     => $this->salesDocumentService->today($workspace),
                'created_by'          => $user->id,
            ]);

            $this->settle($invoice, $allocation);

            return $allocation;
        });

        $what = $advance ? "advance {$advance->number}" : "credit note {$note->number}";

        ActivityLogService::record('created', "Applied {$invoice->currency} {$amount} of {$what} to invoice {$invoice->number}", $invoice, ['source' => 'api']);

        return $this->allocations->loadShown($allocation);
    }

    /** Taken off again, with a reason: the invoice owes it once more, and the advance or credit note has it back. */
    public function remove(Workspace $workspace, User $user, int $id, ?string $reason): SalesAllocation
    {
        $allocation = $this->allocations->findFor($workspace, $id) ?? abort(404, 'Allocation not found.');
        $invoice    = $this->salesDocumentService->find($workspace, DocumentType::Invoice, (int) $allocation->invoice_id);

        DB::transaction(function () use ($user, $reason, $invoice, $allocation) {
            $invoice = $this->rows->lock($invoice);

            if (! $this->rows->lock($allocation)->isActive()) {
                $this->refuse('This was taken off already.');
            }

            $this->allocations->update($allocation, [
                'status'         => SalesAllocation::REMOVED,
                'removed_at'     => now(),
                'removed_by'     => $user->id,
                'removal_reason' => $reason,
            ]);

            $this->settle($invoice, $allocation);
        });

        $what = $allocation->advance ? "advance {$allocation->advance->number}" : "credit note {$allocation->creditNote?->number}";

        ActivityLogService::record('updated', "Took {$invoice->currency} {$allocation->amount} of {$what} off invoice {$invoice->number}", $invoice, ['source' => 'api']);

        return $this->allocations->loadShown($allocation->refresh());
    }

    /** The invoice, and the advance or credit note's own invoice, counted again. */
    private function settle(SalesDocument $invoice, SalesAllocation $allocation): void
    {
        $this->invoiceSettlement->settle($invoice);

        if ($allocation->customer_advance_id) {
            $this->customerAdvanceService->refresh($allocation->advance()->firstOrFail());

            return;
        }

        $own = $allocation->creditNote()->firstOrFail()->source;

        if ($own) {
            $this->invoiceSettlement->settle($own);
        }
    }

    /** An issued invoice of the business with something left to pay, as the "Apply Credit" action asks. */
    private function openInvoice(Workspace $workspace, int $id): SalesDocument
    {
        $invoice = $this->salesDocumentService->find($workspace, DocumentType::Invoice, $id);

        if (! $this->salesDocumentRules->allows($invoice, 'apply_credit', $this->salesDocumentService->today($workspace))) {
            $this->refuse('Only an issued invoice with something still to pay takes credit.');
        }

        return $invoice;
    }

    private function targets(Workspace $workspace, int $contactId, string $currency, ?int $exceptId): Collection
    {
        return $this->allocations->outstandingInvoicesFor($workspace, $contactId, $currency, $exceptId)
            ->map(fn (SalesDocument $invoice) => ['kind' => 'invoice', 'id' => $invoice->id, 'number' => $invoice->number, 'date' => $invoice->document_date, 'currency' => $invoice->currency, 'available' => (string) $invoice->balance_due])
            ->values();
    }
}
