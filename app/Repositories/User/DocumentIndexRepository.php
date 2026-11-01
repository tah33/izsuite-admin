<?php

namespace App\Repositories\User;

use App\Enums\DocumentType;
use App\Enums\PurchaseStatus;
use App\Enums\SalesDocumentStatus;
use App\Models\User\Purchase;
use App\Models\User\SalesDocument;
use App\Models\User\Workspace;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\LazyCollection;

/**
 * The Documents page's index (Doc 06 section 4): the business's transaction
 * documents across its apps as one list - Sales Lite's quotations, proforma
 * invoices, invoices and credit notes, and Purchases Lite's purchases;
 * expenses join with their app. Newest first; searched by number, reference,
 * contact, item or amount, and filtered.
 *
 * Each app's documents stay in their own table: the list is the union of one
 * query per app, paged as one, and each page's rows are then read as the
 * records they are - a SalesDocument or a Purchase - with what their row
 * needs: a credit note's invoice, for what it may still refund.
 */
class DocumentIndexRepository
{
    /** The app families whose documents the index lists - by its source filter. */
    public const SOURCES = ['sales', 'purchases', 'expenses'];

    private const OFFERS = [DocumentType::Quotation->value, DocumentType::Proforma->value];

    private const SALES_WITH = ['contact:id,name', 'location:id,name', 'source:id,amount_paid,refunded_amount'];

    private const PURCHASES_WITH = ['contact:id,name', 'location:id,name'];

    public function paginateFor(Workspace $workspace, array $filters, int $perPage, string $today): LengthAwarePaginator
    {
        $page = $this->union($workspace, $filters, $today)->paginate($perPage);

        return $page->setCollection($this->records($page->getCollection()));
    }

    /** Everything the filters let through, read a few hundred at a time - for an export. */
    public function lazyFor(Workspace $workspace, array $filters, string $today): LazyCollection
    {
        return $this->union($workspace, $filters, $today)->lazy(500)->chunk(500)->flatMap(fn (LazyCollection $rows) => $this->records($rows->collect()));
    }

    public function countFor(Workspace $workspace, array $filters, string $today): int
    {
        return $this->union($workspace, $filters, $today)->count();
    }

    /**
     * Each app's documents the filters let through, as one list of what
     * finds them - their family and id - and what orders them: newest first.
     */
    private function union(Workspace $workspace, array $filters, string $today): QueryBuilder
    {
        $halves = array_values(array_filter([
            $this->wants($filters, 'sales') ? $this->keys($this->sales($workspace, $filters, $today), 'sales') : null,
            $this->wants($filters, 'purchases') ? $this->keys($this->purchases($workspace, $filters, $today), 'purchases') : null,
        ]));

        // Nothing asked for comes from an app with documents: an empty list, shaped like the rest.
        $union = array_shift($halves) ?? $this->keys(SalesDocument::query()->whereRaw('1 = 0'), 'sales');

        foreach ($halves as $half) {
            $union->unionAll($half);
        }

        return DB::query()->fromSub($union, 'documents')
            ->orderByDesc('document_date')
            ->orderByDesc('created_at')
            ->orderByDesc('id');
    }

    /** Whether $family's documents can be among what the filters ask for: its source, a type of its own, a status it has. */
    private function wants(array $filters, string $family): bool
    {
        $type   = $filters['type'] ?? null;
        $status = $filters['status'] ?? null;

        return match ($family) {
            'sales'     => ($filters['source'] ?? 'sales') === 'sales'
                && $type !== DocumentType::Purchase->value
                && $status !== PurchaseStatus::Recorded->value,
            'purchases' => ($filters['source'] ?? 'purchases') === 'purchases'
                && (! $type || $type === DocumentType::Purchase->value)
                && (! $status || in_array($status, [PurchaseStatus::Draft->value, PurchaseStatus::Recorded->value, PurchaseStatus::Void->value], true))
                && ! in_array($filters['payment_status'] ?? null, ['refunded', 'available', 'partially_applied', 'fully_applied'], true),
        };
    }

    /** What the union needs of a document: its family and id, and what orders it. $family is one of this class's own words. */
    private function keys(Builder $query, string $family): QueryBuilder
    {
        return $query->select(['id', 'document_date', 'created_at'])->selectRaw("'{$family}' as family")->toBase();
    }

    /** The rows of a page, read as the records they are, in the page's order. */
    private function records(Collection $rows): Collection
    {
        $loaded = [
            'sales'     => SalesDocument::with(self::SALES_WITH)->findMany($rows->where('family', 'sales')->pluck('id'))->keyBy('id'),
            'purchases' => Purchase::with(self::PURCHASES_WITH)->findMany($rows->where('family', 'purchases')->pluck('id'))->keyBy('id'),
        ];

        return $rows->map(fn (object $row) => $loaded[$row->family][$row->id])->values();
    }

    private function sales(Workspace $workspace, array $filters, string $today): Builder
    {
        return SalesDocument::query()
            ->where('workspace_id', $workspace->id)
            ->when($filters['search'] ?? null, fn (Builder $query, string $search) => $this->search($query, $search, ['customer_reference', 'sales_reference']))
            ->when($filters['type'] ?? null, fn (Builder $query, string $type) => $query->where('type', $type))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $this->whereStatus($query, $status, $today))
            ->when($filters['payment_status'] ?? null, fn (Builder $query, string $status) => $query->where('payment_status', $status))
            ->when(filter_var($filters['overdue'] ?? false, FILTER_VALIDATE_BOOLEAN), fn (Builder $query) => $query
                ->where('type', DocumentType::Invoice->value)
                ->where('status', SalesDocumentStatus::Issued->value)
                ->where('due_date', '<', $today)
                ->where('balance_due', '>', 0))
            ->tap(fn (Builder $query) => $this->common($query, $filters));
    }

    private function purchases(Workspace $workspace, array $filters, string $today): Builder
    {
        return Purchase::query()
            ->where('workspace_id', $workspace->id)
            ->when($filters['search'] ?? null, fn (Builder $query, string $search) => $this->search($query, $search, ['reference', 'supplier_invoice_number']))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['payment_status'] ?? null, fn (Builder $query, string $status) => $query->where('payment_status', $status))
            ->when(filter_var($filters['overdue'] ?? false, FILTER_VALIDATE_BOOLEAN), fn (Builder $query) => $query
                ->where('status', PurchaseStatus::Recorded->value)
                ->where('due_date', '<', $today)
                ->where('balance_due', '>', 0))
            ->tap(fn (Builder $query) => $this->common($query, $filters));
    }

    /** The filters every app's documents share. */
    private function common(Builder $query, array $filters): void
    {
        $query
            ->when($filters['contact_id'] ?? null, fn (Builder $query, $id) => $query->where('contact_id', $id))
            ->when($filters['location_id'] ?? null, fn (Builder $query, $id) => $query->where('location_id', $id))
            ->when($filters['currency'] ?? null, fn (Builder $query, string $currency) => $query->where('currency', $currency))
            ->when($filters['date_from'] ?? null, fn (Builder $query, string $date) => $query->where('document_date', '>=', $date))
            ->when($filters['date_to'] ?? null, fn (Builder $query, string $date) => $query->where('document_date', '<=', $date));
    }

    /** By number, the references in $references, the contact's name, a line's item - or the total, typed as a number. */
    private function search(Builder $query, string $search, array $references): Builder
    {
        $amount = str_replace(',', '', $search);

        return $query->where(function (Builder $query) use ($search, $references, $amount) {
            $query->where('number', 'like', "%{$search}%");

            foreach ($references as $column) {
                $query->orWhere($column, 'like', "%{$search}%");
            }

            $query->orWhereHas('contact', fn (Builder $query) => $query->where('name', 'like', "%{$search}%"))
                ->orWhereHas('lines', fn (Builder $query) => $query->where('name', 'like', "%{$search}%"))
                ->when(is_numeric($amount), fn (Builder $query) => $query->orWhere('grand_total', $amount));
        });
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
