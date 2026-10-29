<?php

namespace App\Repositories\User;

use App\Enums\DocumentType;
use App\Enums\SalesDocumentStatus;
use App\Models\User\Contact;
use App\Models\User\Item;
use App\Models\User\Location;
use App\Models\User\SalesDocument;
use App\Models\User\SalesDocumentLine;
use App\Models\User\SalesDocumentRevision;
use App\Models\User\TaxRate;
use App\Models\User\Workspace;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Sales documents belong to one business and are of one type. Every lookup is
 * scoped to both, so another business's document - or an invoice asked for as
 * a quotation - is simply not found.
 *
 * Statuses are filtered as the page shows them: a draft or sent quotation or
 * proforma past its expiry date counts as expired, so $today is passed in.
 */
class SalesDocumentRepository
{
    /**
     * What a document's page shows with it: an invoice its payments and credit
     * notes, a credit note its refunds and - for what it may still refund -
     * what its invoice was paid.
     */
    private const SHOWN_WITH = [
        'lines', 'contact', 'location:id,name',
        'source:id,type,number,status,amount_paid,refunded_amount',
        'conversions:id,source_id,type,number,status',
        'creditNotes:id,source_id,type,number,status,document_date,grand_total,applied_amount,refunded_amount,balance_due,payment_status',
        'payments.account:id,name,currency',
        'activeLink', 'emails.sender:id,first_name,last_name',
    ];

    public function paginateFor(Workspace $workspace, DocumentType $type, array $filters, int $perPage, string $today): LengthAwarePaginator
    {
        return SalesDocument::query()
            ->where('workspace_id', $workspace->id)
            ->where('type', $type->value)
            ->with(['contact:id,name', 'location:id,name', 'source:id,number'])
            ->when($filters['search'] ?? null, fn (Builder $query, string $search) => $query->where(fn (Builder $query) => $query
                ->where('number', 'like', "%{$search}%")
                ->orWhere('customer_reference', 'like', "%{$search}%")
                ->orWhere('sales_reference', 'like', "%{$search}%")
                ->orWhereHas('contact', fn (Builder $query) => $query->where('name', 'like', "%{$search}%"))))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $this->whereStatus($query, $type, $status, $today))
            ->when($filters['payment_status'] ?? null, fn (Builder $query, string $status) => $status === 'outstanding'
                ? $query->where('status', SalesDocumentStatus::Issued->value)->where('balance_due', '>', 0)
                : $query->where('payment_status', $status))
            ->when(filter_var($filters['overdue'] ?? false, FILTER_VALIDATE_BOOLEAN), fn (Builder $query) => $query
                ->where('status', SalesDocumentStatus::Issued->value)
                ->where('due_date', '<', $today)
                ->where('balance_due', '>', 0))
            ->when($filters['contact_id'] ?? null, fn (Builder $query, $id) => $query->where('contact_id', $id))
            ->when($filters['location_id'] ?? null, fn (Builder $query, $id) => $query->where('location_id', $id))
            ->when($filters['currency'] ?? null, fn (Builder $query, string $currency) => $query->where('currency', $currency))
            ->when($filters['date_from'] ?? null, fn (Builder $query, string $date) => $query->where('document_date', '>=', $date))
            ->when($filters['date_to'] ?? null, fn (Builder $query, string $date) => $query->where('document_date', '<=', $date))
            ->orderByDesc('document_date')
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    public function findFor(Workspace $workspace, DocumentType $type, int $id): ?SalesDocument
    {
        return $workspace->salesDocuments()->where('type', $type->value)->with(self::SHOWN_WITH)->find($id);
    }

    /** Read again after a save, with what its page shows. */
    public function loadShown(SalesDocument $document): SalesDocument
    {
        return $document->load(self::SHOWN_WITH);
    }

    /** With what its PDF shows - for one drawn away from its page: an email, a secure link. */
    public function loadForPdf(SalesDocument $document): SalesDocument
    {
        return $document->load(['lines', 'contact', 'location:id,name', 'source:id,type,number']);
    }

    /** Its lines with the rates their taxes came from, as those are now. */
    public function loadLineTaxes(SalesDocument $document): SalesDocument
    {
        return $document->load('lines.taxRate');
    }

    /** An invoice's issued credit notes, in the order they were issued - the order they settle it in. */
    public function issuedCreditNotesOf(SalesDocument $invoice): Collection
    {
        return SalesDocument::query()
            ->where('source_id', $invoice->id)
            ->where('type', DocumentType::CreditNote->value)
            ->where('status', SalesDocumentStatus::Issued->value)
            ->orderBy('issued_at')
            ->orderBy('id')
            ->get();
    }

    /** The lines of $invoice's issued credit notes - what each invoice line has been credited already. */
    public function creditedLinesOf(SalesDocument $invoice): Collection
    {
        return SalesDocumentLine::query()
            ->whereIn('source_line_id', $invoice->lines->pluck('id'))
            ->whereHas('document', fn (Builder $query) => $query
                ->where('type', DocumentType::CreditNote->value)
                ->where('status', SalesDocumentStatus::Issued->value))
            ->get(['id', 'source_line_id', 'quantity', 'amount']);
    }

    /** An issued invoice of the business, with its lines - one a credit note can be made from. */
    public function findIssuedInvoiceFor(Workspace $workspace, int $id): ?SalesDocument
    {
        return $workspace->salesDocuments()
            ->where('type', DocumentType::Invoice->value)
            ->where('status', SalesDocumentStatus::Issued->value)
            ->with(['lines', 'contact'])
            ->find($id);
    }

    /** Whether one of the business's $type documents already has $number. */
    public function numberTaken(Workspace $workspace, DocumentType $type, string $number): bool
    {
        return $workspace->salesDocuments()->where('type', $type->value)->where('number', $number)->exists();
    }

    /** Made through the business, so its workspace is never read from $data. */
    public function create(Workspace $workspace, array $data): SalesDocument
    {
        return $workspace->salesDocuments()->create($data);
    }

    public function update(SalesDocument $document, array $data): SalesDocument
    {
        $document->update($data);

        return $document;
    }

    /** Its lines become $rows, in their order. */
    public function replaceLines(SalesDocument $document, array $rows): void
    {
        $document->lines()->delete();
        $document->lines()->createMany($rows);
        $document->unsetRelation('lines');
    }

    public function delete(SalesDocument $document): void
    {
        $document->delete();
    }

    /* ---------------------------------------------------------- revisions */

    public function addRevision(SalesDocument $document, int $revision, array $snapshot, ?int $userId): SalesDocumentRevision
    {
        return $document->revisions()->create(['revision' => $revision, 'snapshot' => $snapshot, 'created_by' => $userId]);
    }

    public function revisionsOf(SalesDocument $document): Collection
    {
        return $document->revisions()->with('creator:id,first_name,last_name')->get(['id', 'sales_document_id', 'revision', 'created_by', 'created_at']);
    }

    public function findRevision(SalesDocument $document, int $revision): ?SalesDocumentRevision
    {
        return $document->revisions()->where('revision', $revision)->first();
    }

    /* ---------------------------------------------------------- what is in use */

    public function usesContact(Contact $contact): bool
    {
        return SalesDocument::where('contact_id', $contact->id)->exists();
    }

    public function usesItem(Item $item): bool
    {
        return SalesDocumentLine::where('item_id', $item->id)->exists();
    }

    public function usesTaxRate(TaxRate $rate): bool
    {
        return SalesDocumentLine::where('tax_rate_id', $rate->id)->exists();
    }

    public function usesLocation(Location $location): bool
    {
        return SalesDocument::where('location_id', $location->id)->exists();
    }

    /** Whether the business has numbered any document - issued or sent: a transaction. */
    public function hasTransactions(Workspace $workspace): bool
    {
        return $workspace->salesDocuments()->whereNotNull('number')->exists();
    }

    /**
     * As the page counts statuses: draft and sent leave out what is past its
     * expiry date, and expired is exactly that.
     */
    private function whereStatus(Builder $query, DocumentType $type, string $status, string $today): Builder
    {
        $open = [SalesDocumentStatus::Draft->value, SalesDocumentStatus::Sent->value];

        if ($type->isOffer() && $status === SalesDocumentStatus::EXPIRED) {
            return $query->whereIn('status', $open)->where('expiry_date', '<', $today);
        }

        if ($type->isOffer() && in_array($status, $open, true)) {
            return $query->where('status', $status)->where(fn (Builder $query) => $query->whereNull('expiry_date')->orWhere('expiry_date', '>=', $today));
        }

        return $query->where('status', $status);
    }
}
