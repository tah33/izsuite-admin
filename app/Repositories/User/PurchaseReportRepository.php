<?php

namespace App\Repositories\User;

use App\Enums\PurchaseStatus;
use App\Models\User\Item;
use App\Models\User\Purchase;
use App\Models\User\PurchaseLine;
use App\Models\User\SupplierPayment;
use App\Models\User\Workspace;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * What Basic Reports and the Dashboard read of Purchases Lite (Doc 07, Doc
 * 02): recorded purchases by their own dates, their lines, completed supplier
 * payments by theirs, and purchases as they stood on a day. Never a draft or
 * void purchase, nor a reversed payment (Doc 09 section 9). Every query is the
 * business's own.
 *
 * $filters narrow them: location_id, contact_id, currency - a purchase's own,
 * or a payment's account's - payment_account_id for money out of one account,
 * and item_id or category_id for lines of one item or category.
 */
class PurchaseReportRepository
{
    private const FIGURES = [
        'id', 'number', 'status', 'document_date', 'due_date', 'contact_id', 'location_id', 'currency', 'exchange_rate', 'price_tax_mode',
        'subtotal', 'discount_total', 'tax_total', 'grand_total', 'net_total', 'base_grand_total', 'base_tax_total', 'base_net_total', 'taxes',
        'amount_paid', 'withholding_amount', 'balance_due', 'payment_status', 'supplier_snapshot', 'supplier_invoice_number',
    ];

    /** Recorded purchases dated from $from to $to, oldest first. */
    public function recorded(Workspace $workspace, string $from, string $to, array $filters = []): Collection
    {
        return $this->purchases($workspace, $filters)
            ->whereBetween('document_date', [$from, $to])
            ->with(['contact:id,name', 'location:id,name'])
            ->orderBy('document_date')
            ->orderBy('id')
            ->get(self::FIGURES);
    }

    /**
     * The lines of recorded purchases dated from $from to $to, with what their
     * share of the totals needs, their tax as they kept it - recoverable or
     * not - and where their purchase falls: its date, supplier, location and
     * currency. An item's category is the one it has now.
     */
    public function recordedLines(Workspace $workspace, string $from, string $to, array $filters = []): Collection
    {
        return PurchaseLine::query()
            ->join('purchases', 'purchases.id', '=', 'purchase_lines.purchase_id')
            ->leftJoin('contacts', 'contacts.id', '=', 'purchases.contact_id')
            ->leftJoin('locations', 'locations.id', '=', 'purchases.location_id')
            ->leftJoin('items', 'items.id', '=', 'purchase_lines.item_id')
            ->leftJoin('item_categories', 'item_categories.id', '=', 'items.category_id')
            ->where('purchases.workspace_id', $workspace->id)
            ->where('purchases.status', PurchaseStatus::Recorded->value)
            ->whereBetween('purchases.document_date', [$from, $to])
            ->when($filters['location_id'] ?? null, fn (Builder $query, $id) => $query->where('purchases.location_id', $id))
            ->when(isset($filters['location_ids']), fn (Builder $query) => $query->whereIn('purchases.location_id', $filters['location_ids']))
            ->when($filters['contact_id'] ?? null, fn (Builder $query, $id) => $query->where('purchases.contact_id', $id))
            ->when($filters['currency'] ?? null, fn (Builder $query, string $currency) => $query->where('purchases.currency', $currency))
            ->when($filters['item_id'] ?? null, fn (Builder $query, $id) => $query->where('purchase_lines.item_id', $id))
            ->when($filters['category_id'] ?? null, fn (Builder $query, $id) => $query->whereIn(
                'purchase_lines.item_id',
                Item::query()->where('workspace_id', $workspace->id)->where('category_id', $id)->select('id'),
            ))
            ->get([
                'purchase_lines.item_id', 'purchase_lines.name', 'purchase_lines.quantity', 'purchase_lines.amount', 'purchase_lines.tax_amount',
                'purchase_lines.tax_name', 'purchase_lines.tax_code', 'purchase_lines.tax_rate', 'purchase_lines.tax_treatment',
                'purchase_lines.tax_recoverability', 'purchase_lines.purchase_id', 'purchases.number', 'purchases.subtotal',
                'purchases.discount_total', 'purchases.price_tax_mode', 'purchases.exchange_rate', 'purchases.document_date',
                'purchases.contact_id', 'purchases.location_id', 'purchases.currency', 'purchases.supplier_snapshot',
                'contacts.name as contact_name', 'locations.name as location_name', 'items.category_id', 'item_categories.name as category_name',
            ]);
    }

    /** Whether any recorded purchase has a taxed line - zero-rated and exempt ones too: the Tax Summary's history. */
    public function hasTaxHistory(Workspace $workspace): bool
    {
        return PurchaseLine::query()
            ->whereNotNull('tax_name')
            ->whereHas('purchase', fn (Builder $query) => $query->where('workspace_id', $workspace->id)->where('status', PurchaseStatus::Recorded->value))
            ->exists();
    }

    /** Whether any completed supplier payment had tax withheld - the history that keeps Tax Withheld showing. */
    public function hasWithholdingHistory(Workspace $workspace): bool
    {
        return SupplierPayment::query()
            ->where('workspace_id', $workspace->id)
            ->where('status', SupplierPayment::COMPLETED)
            ->where('withholding_amount', '>', 0)
            ->exists();
    }

    /** Completed supplier payments dated from $from to $to, oldest first: money that went out to suppliers. */
    public function paymentsMade(Workspace $workspace, string $from, string $to, array $filters = []): Collection
    {
        return SupplierPayment::query()
            ->where('workspace_id', $workspace->id)
            ->where('status', SupplierPayment::COMPLETED)
            ->whereBetween('payment_date', [$from, $to])
            ->when($filters['location_id'] ?? null, fn (Builder $query, $id) => $query->where('location_id', $id))
            ->when(isset($filters['location_ids']), fn (Builder $query) => $query->whereIn('location_id', $filters['location_ids']))
            ->when($filters['contact_id'] ?? null, fn (Builder $query, $id) => $query->where('contact_id', $id))
            ->when($filters['payment_account_id'] ?? null, fn (Builder $query, $id) => $query->where('payment_account_id', $id))
            ->when($filters['currency'] ?? null, fn (Builder $query, string $currency) => $query->whereHas('account', fn (Builder $query) => $query->where('currency', $currency)))
            ->when($filters['document_currency'] ?? null, fn (Builder $query, string $currency) => $query->whereHas('purchase', fn (Builder $query) => $query->where('currency', $currency)))
            ->with(['purchase:id,number,currency,exchange_rate', 'contact:id,name', 'account:id,name,currency', 'location:id,name'])
            ->orderBy('payment_date')
            ->orderBy('id')
            ->get();
    }

    /**
     * Recorded purchases dated on or before $asOf, each with what had paid it
     * by then (paid_by, withheld_by) - the parts of PurchaseSettlement's
     * balance, as of that day.
     */
    public function purchasesAsOf(Workspace $workspace, string $asOf, array $filters = []): Collection
    {
        $payments = SupplierPayment::query()
            ->selectRaw('purchase_id, SUM(applied_amount) as paid_sum, SUM(withholding_amount) as withheld_sum')
            ->where('workspace_id', $workspace->id)
            ->where('status', SupplierPayment::COMPLETED)
            ->where('payment_date', '<=', $asOf)
            ->groupBy('purchase_id');

        return $this->purchases($workspace, $filters, 'purchases.')
            ->select(array_map(fn (string $column) => "purchases.{$column}", self::FIGURES))
            ->selectRaw('COALESCE(p.paid_sum, 0) as paid_by, COALESCE(p.withheld_sum, 0) as withheld_by')
            ->leftJoinSub($payments, 'p', 'p.purchase_id', '=', 'purchases.id')
            ->where('purchases.document_date', '<=', $asOf)
            ->with(['contact:id,name', 'location:id,name'])
            ->orderBy('purchases.due_date')
            ->orderBy('purchases.id')
            ->get();
    }

    /** The newest purchases, drafts and void ones too - the Dashboard's Recent Transactions. */
    public function latestPurchases(Workspace $workspace, int $limit, ?int $locationId, ?array $locationIds = null): Collection
    {
        return Purchase::query()
            ->where('workspace_id', $workspace->id)
            ->when($locationId, fn (Builder $query, int $id) => $query->where('location_id', $id))
            ->atLocations($locationIds)
            ->with('contact:id,name')
            ->orderByDesc('document_date')
            ->orderByDesc('id')
            ->limit($limit)
            ->get(['id', 'number', 'status', 'document_date', 'contact_id', 'currency', 'grand_total', 'supplier_snapshot', 'created_at']);
    }

    /** The newest supplier payments, reversed ones too. */
    public function latestPayments(Workspace $workspace, int $limit, ?int $locationId, ?array $locationIds = null): Collection
    {
        return SupplierPayment::query()
            ->where('workspace_id', $workspace->id)
            ->when($locationId, fn (Builder $query, int $id) => $query->where('location_id', $id))
            ->atLocations($locationIds)
            ->with(['contact:id,name', 'account:id,currency'])
            ->orderByDesc('payment_date')
            ->orderByDesc('id')
            ->limit($limit)
            ->get(['id', 'number', 'status', 'payment_date', 'contact_id', 'payment_account_id', 'amount', 'created_at']);
    }

    /** Recorded purchases with $filters - columns prefixed with $table where the query joins. */
    private function purchases(Workspace $workspace, array $filters, string $table = ''): Builder
    {
        return Purchase::query()
            ->where("{$table}workspace_id", $workspace->id)
            ->where("{$table}status", PurchaseStatus::Recorded->value)
            ->when($filters['location_id'] ?? null, fn (Builder $query, $id) => $query->where("{$table}location_id", $id))
            ->when(isset($filters['location_ids']), fn (Builder $query) => $query->whereIn("{$table}location_id", $filters['location_ids']))
            ->when($filters['contact_id'] ?? null, fn (Builder $query, $id) => $query->where("{$table}contact_id", $id))
            ->when($filters['currency'] ?? null, fn (Builder $query, string $currency) => $query->where("{$table}currency", $currency));
    }
}
