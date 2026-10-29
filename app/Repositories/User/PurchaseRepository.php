<?php

namespace App\Repositories\User;

use App\Enums\PurchaseStatus;
use App\Models\User\Contact;
use App\Models\User\Item;
use App\Models\User\Location;
use App\Models\User\Purchase;
use App\Models\User\PurchaseLine;
use App\Models\User\TaxRate;
use App\Models\User\Workspace;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\LazyCollection;

/**
 * The purchases table and its lines (Doc 09). Every query is the business's
 * own. The list is searched by number, supplier invoice number, supplier,
 * item or reference, and filtered by status, payment - overdue among it -
 * supplier, location, currency and dates (section 8), newest first.
 */
class PurchaseRepository
{
    /** What a purchase's page and edit form show with it. */
    private const SHOWN_WITH = ['lines', 'contact', 'location:id,name'];

    public function paginateFor(Workspace $workspace, array $filters, int $perPage, string $today): LengthAwarePaginator
    {
        return $this->query($workspace, $filters, $today)->paginate($perPage);
    }

    /** Everything the filters let through, read a few hundred at a time - for an export. */
    public function lazyFor(Workspace $workspace, array $filters, string $today): LazyCollection
    {
        return $this->query($workspace, $filters, $today)->lazy(500);
    }

    public function findFor(Workspace $workspace, int $id): ?Purchase
    {
        return $workspace->purchases()->with(self::SHOWN_WITH)->find($id);
    }

    /** Read again after a save, with what its page shows. */
    public function loadShown(Purchase $purchase): Purchase
    {
        return $purchase->load(self::SHOWN_WITH);
    }

    /** Its lines with the rates their taxes came from, as those are now. */
    public function loadLineTaxes(Purchase $purchase): Purchase
    {
        return $purchase->load('lines.taxRate');
    }

    /** Whether one of the business's purchases already has $number. */
    public function numberTaken(Workspace $workspace, string $number): bool
    {
        return $workspace->purchases()->where('number', $number)->exists();
    }

    /**
     * Supplier invoices recorded before (Doc 09 section 11): the business's
     * other recorded purchases from the same supplier with the same supplier
     * invoice number, oldest first.
     */
    public function duplicatesOf(Purchase $purchase): Collection
    {
        return Purchase::query()
            ->where('workspace_id', $purchase->workspace_id)
            ->where('id', '!=', $purchase->id)
            ->where('status', PurchaseStatus::Recorded->value)
            ->where('contact_id', $purchase->contact_id)
            ->where('supplier_invoice_number', $purchase->supplier_invoice_number)
            ->orderBy('id')
            ->get(['id', 'number', 'document_date', 'supplier_invoice_date', 'currency', 'grand_total']);
    }

    /** Made through the business, so its workspace is never read from $data. */
    public function create(Workspace $workspace, array $data): Purchase
    {
        return $workspace->purchases()->create($data);
    }

    public function update(Purchase $purchase, array $data): Purchase
    {
        $purchase->update($data);

        return $purchase;
    }

    /** Its lines become $rows, in their order. */
    public function replaceLines(Purchase $purchase, array $rows): void
    {
        $purchase->lines()->delete();
        $purchase->lines()->createMany($rows);
        $purchase->unsetRelation('lines');
    }

    public function delete(Purchase $purchase): void
    {
        $purchase->delete();
    }

    /* ---------------------------------------------------------- what is in use */

    public function usesContact(Contact $contact): bool
    {
        return Purchase::where('contact_id', $contact->id)->exists();
    }

    public function usesItem(Item $item): bool
    {
        return PurchaseLine::where('item_id', $item->id)->exists();
    }

    public function usesTaxRate(TaxRate $rate): bool
    {
        return PurchaseLine::where('tax_rate_id', $rate->id)->exists();
    }

    public function usesLocation(Location $location): bool
    {
        return Purchase::where('location_id', $location->id)->exists();
    }

    /** Whether the business has recorded any purchase: a transaction. */
    public function hasTransactions(Workspace $workspace): bool
    {
        return $workspace->purchases()->whereNotNull('number')->exists();
    }

    private function query(Workspace $workspace, array $filters, string $today): Builder
    {
        return Purchase::query()
            ->where('workspace_id', $workspace->id)
            ->with(['contact:id,name', 'location:id,name'])
            ->when($filters['search'] ?? null, fn (Builder $query, string $search) => $query->where(fn (Builder $query) => $query
                ->where('number', 'like', "%{$search}%")
                ->orWhere('supplier_invoice_number', 'like', "%{$search}%")
                ->orWhere('reference', 'like', "%{$search}%")
                ->orWhereHas('contact', fn (Builder $query) => $query->where('name', 'like', "%{$search}%"))
                ->orWhereHas('lines', fn (Builder $query) => $query->where('name', 'like', "%{$search}%"))))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['payment_status'] ?? null, fn (Builder $query, string $status) => $status === 'outstanding'
                ? $query->where('status', PurchaseStatus::Recorded->value)->where('balance_due', '>', 0)
                : $query->where('payment_status', $status))
            ->when(filter_var($filters['overdue'] ?? false, FILTER_VALIDATE_BOOLEAN), fn (Builder $query) => $query
                ->where('status', PurchaseStatus::Recorded->value)
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
}
