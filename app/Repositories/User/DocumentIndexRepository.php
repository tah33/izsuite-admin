<?php

namespace App\Repositories\User;

use App\Enums\DocumentType;
use App\Enums\SalesDocumentStatus;
use App\Models\User\SalesDocument;
use App\Models\User\Workspace;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\LazyCollection;

/**
 * The Documents page's index (Doc 06 section 4): the business's transaction
 * documents across its apps as one list - so far Sales Lite's quotations,
 * proforma invoices, invoices and credit notes; purchases and expenses join
 * with their apps. Newest first; searched by number, reference, contact,
 * item or amount, and filtered. Each comes with what its actions need: a
 * credit note's invoice, for what it may still refund.
 */
class DocumentIndexRepository
{
    /** The app families whose documents the index lists - by its source filter. */
    public const SOURCES = ['sales', 'purchases', 'expenses'];

    private const OFFERS = [DocumentType::Quotation->value, DocumentType::Proforma->value];

    public function paginateFor(Workspace $workspace, array $filters, int $perPage, string $today): LengthAwarePaginator
    {
        return $this->query($workspace, $filters, $today)->paginate($perPage);
    }

    /** Everything the filters let through, read a few hundred at a time - for an export. */
    public function lazyFor(Workspace $workspace, array $filters, string $today): LazyCollection
    {
        return $this->query($workspace, $filters, $today)->lazy(500);
    }

    public function countFor(Workspace $workspace, array $filters, string $today): int
    {
        return $this->query($workspace, $filters, $today)->count();
    }

    private function query(Workspace $workspace, array $filters, string $today): Builder
    {
        return SalesDocument::query()
            ->where('workspace_id', $workspace->id)
            ->with(['contact:id,name', 'location:id,name', 'source:id,amount_paid,refunded_amount'])
            // Only Sales keeps documents so far: another app's source has none yet.
            ->when(($filters['source'] ?? 'sales') !== 'sales', fn (Builder $query) => $query->whereRaw('1 = 0'))
            ->when($filters['search'] ?? null, fn (Builder $query, string $search) => $this->search($query, $search))
            ->when($filters['type'] ?? null, fn (Builder $query, string $type) => $query->where('type', $type))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $this->whereStatus($query, $status, $today))
            ->when($filters['payment_status'] ?? null, fn (Builder $query, string $status) => $query->where('payment_status', $status))
            ->when(filter_var($filters['overdue'] ?? false, FILTER_VALIDATE_BOOLEAN), fn (Builder $query) => $query
                ->where('type', DocumentType::Invoice->value)
                ->where('status', SalesDocumentStatus::Issued->value)
                ->where('due_date', '<', $today)
                ->where('balance_due', '>', 0))
            ->when($filters['contact_id'] ?? null, fn (Builder $query, $id) => $query->where('contact_id', $id))
            ->when($filters['location_id'] ?? null, fn (Builder $query, $id) => $query->where('location_id', $id))
            ->when($filters['currency'] ?? null, fn (Builder $query, string $currency) => $query->where('currency', $currency))
            ->when($filters['date_from'] ?? null, fn (Builder $query, string $date) => $query->where('document_date', '>=', $date))
            ->when($filters['date_to'] ?? null, fn (Builder $query, string $date) => $query->where('document_date', '<=', $date))
            ->orderByDesc('document_date')
            ->orderByDesc('id');
    }

    /** By number, either reference, the contact's name, a line's item - or the total, typed as a number. */
    private function search(Builder $query, string $search): Builder
    {
        $amount = str_replace(',', '', $search);

        return $query->where(fn (Builder $query) => $query
            ->where('number', 'like', "%{$search}%")
            ->orWhere('customer_reference', 'like', "%{$search}%")
            ->orWhere('sales_reference', 'like', "%{$search}%")
            ->orWhereHas('contact', fn (Builder $query) => $query->where('name', 'like', "%{$search}%"))
            ->orWhereHas('lines', fn (Builder $query) => $query->where('name', 'like', "%{$search}%"))
            ->when(is_numeric($amount), fn (Builder $query) => $query->orWhere('grand_total', $amount)));
    }

    /** A draft or sent quotation or proforma past its expiry date reads as expired, and as nothing else. */
    private function whereStatus(Builder $query, string $status, string $today): Builder
    {
        $open = [SalesDocumentStatus::Draft->value, SalesDocumentStatus::Sent->value];

        if ($status === SalesDocumentStatus::EXPIRED) {
            return $query->whereIn('type', self::OFFERS)->whereIn('status', $open)->where('expiry_date', '<', $today);
        }

        if (in_array($status, $open, true)) {
            return $query->where('status', $status)->where(fn (Builder $query) => $query
                ->whereNotIn('type', self::OFFERS)
                ->orWhereNull('expiry_date')
                ->orWhere('expiry_date', '>=', $today));
        }

        return $query->where('status', $status);
    }
}
