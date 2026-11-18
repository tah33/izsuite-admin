<?php

namespace App\Repositories\User;

use App\Enums\DocumentType;
use App\Enums\SalesDocumentStatus;
use App\Models\User\CustomerAdvance;
use App\Models\User\SalesAllocation;
use App\Models\User\SalesDocument;
use App\Models\User\Workspace;
use App\Support\Decimal;
use Illuminate\Database\Eloquent\Collection;

/**
 * Advances and credit notes applied to invoices (Doc 16 Phase D1), and the
 * documents on either side of one: a customer's credit notes with credit left,
 * and their issued invoices still owing.
 */
class SalesAllocationRepository
{
    private const SHOWN_WITH = ['invoice:id,number', 'advance:id,number', 'creditNote:id,number', 'creator:id,first_name,last_name', 'remover:id,first_name,last_name'];

    public function findFor(Workspace $workspace, int $id): ?SalesAllocation
    {
        return $workspace->salesAllocations()->with(self::SHOWN_WITH)->find($id);
    }

    public function loadShown(SalesAllocation $allocation): SalesAllocation
    {
        return $allocation->load(self::SHOWN_WITH);
    }

    public function create(Workspace $workspace, array $data): SalesAllocation
    {
        return $workspace->salesAllocations()->create($data);
    }

    public function update(SalesAllocation $allocation, array $data): SalesAllocation
    {
        $allocation->update($data);

        return $allocation;
    }

    /** What active allocations take off an invoice. */
    public function sumTo(SalesDocument $invoice): string
    {
        return $this->sum('invoice_id', $invoice->id);
    }

    /** What a credit note has applied to other invoices. */
    public function sumFromNote(SalesDocument $note): string
    {
        return $this->sum('credit_note_id', $note->id);
    }

    public function sumFromAdvance(CustomerAdvance $advance): string
    {
        return $this->sum('customer_advance_id', $advance->id);
    }

    public function hasActiveFromAdvance(CustomerAdvance $advance): bool
    {
        return SalesAllocation::query()->where('customer_advance_id', $advance->id)->where('status', SalesAllocation::ACTIVE)->exists();
    }

    /** An invoice's allocations in, or a credit note's out, the removed ones too, oldest first. */
    public function historyOf(SalesDocument $document): Collection
    {
        return SalesAllocation::query()
            ->where($document->type === DocumentType::CreditNote ? 'credit_note_id' : 'invoice_id', $document->id)
            ->with(self::SHOWN_WITH)
            ->orderBy('id')
            ->get();
    }

    /** A customer's issued credit notes with credit left in $currency, but those of $exceptInvoiceId: they apply to it by themselves. */
    public function openCreditNotesFor(Workspace $workspace, int $contactId, string $currency, ?int $exceptInvoiceId = null): Collection
    {
        return $workspace->salesDocuments()
            ->where('type', 'credit_note')
            ->where('contact_id', $contactId)
            ->where('currency', $currency)
            ->where('status', SalesDocumentStatus::Issued->value)
            ->where('balance_due', '>', 0)
            ->when($exceptInvoiceId, fn ($query, $id) => $query->where(fn ($query) => $query->whereNull('source_id')->orWhere('source_id', '!=', $id)))
            ->orderBy('document_date')
            ->orderBy('id')
            ->get(['id', 'number', 'document_date', 'currency', 'balance_due', 'source_id']);
    }

    /** A customer's issued invoices still owing in $currency, oldest first, but $exceptId. */
    public function outstandingInvoicesFor(Workspace $workspace, int $contactId, string $currency, ?int $exceptId = null): Collection
    {
        return $workspace->salesDocuments()
            ->where('type', 'invoice')
            ->where('contact_id', $contactId)
            ->where('currency', $currency)
            ->where('status', SalesDocumentStatus::Issued->value)
            ->where('balance_due', '>', 0)
            ->when($exceptId, fn ($query, $id) => $query->whereKeyNot($id))
            ->orderBy('document_date')
            ->orderBy('id')
            ->get(['id', 'number', 'document_date', 'currency', 'balance_due']);
    }

    private function sum(string $column, int $id): string
    {
        return Decimal::sum(SalesAllocation::query()->where($column, $id)->where('status', SalesAllocation::ACTIVE)->pluck('amount'));
    }
}
