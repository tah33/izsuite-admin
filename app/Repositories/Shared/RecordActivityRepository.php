<?php

namespace App\Repositories\Shared;

use App\Models\Shared\ActivityLog;
use App\Models\User\Expense;
use App\Models\User\ExpensePayment;
use App\Models\User\Purchase;
use App\Models\User\SalesDocument;
use App\Models\User\SalesPayment;
use App\Models\User\SupplierPayment;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * A record's activity timeline (Doc 14 section 14.14 - Phase D), from the
 * Activity Log: the entries about the record itself and about the money
 * that settled it. Newest first, each with who did it.
 */
class RecordActivityRepository
{
    /**
     * A sales document's own entries and those of its payments or refunds.
     * The documents made from it (a conversion, a credit note) add the entry
     * that made them.
     */
    public function forSalesDocument(SalesDocument $document, int $limit): Collection
    {
        return $this->entries($document->workspace_id, fn (Builder $query) => $query
            ->where(fn (Builder $query) => $this->about($query, SalesDocument::class, [$document->id]))
            ->orWhere(fn (Builder $query) => $this->about($query, SalesPayment::class, SalesPayment::query()->where('sales_document_id', $document->id)->select('id')))
            ->orWhere(fn (Builder $query) => $this->about($query, SalesDocument::class, SalesDocument::query()->where('source_id', $document->id)->select('id'))->where('action', 'created')), $limit);
    }

    public function forPurchase(Purchase $purchase, int $limit): Collection
    {
        return $this->entries($purchase->workspace_id, fn (Builder $query) => $query
            ->where(fn (Builder $query) => $this->about($query, Purchase::class, [$purchase->id]))
            ->orWhere(fn (Builder $query) => $this->about($query, SupplierPayment::class, SupplierPayment::query()->where('purchase_id', $purchase->id)->select('id'))), $limit);
    }

    public function forExpense(Expense $expense, int $limit): Collection
    {
        return $this->entries($expense->workspace_id, fn (Builder $query) => $query
            ->where(fn (Builder $query) => $this->about($query, Expense::class, [$expense->id]))
            ->orWhere(fn (Builder $query) => $this->about($query, ExpensePayment::class, ExpensePayment::query()->where('expense_id', $expense->id)->select('id'))), $limit);
    }

    /** @param  list<int>|Builder  $ids */
    private function about(Builder $query, string $type, array|Builder $ids): Builder
    {
        return $query->where('model_type', $type)->whereIn('model_id', $ids);
    }

    private function entries(int $workspaceId, Closure $about, int $limit): Collection
    {
        return ActivityLog::query()
            ->where('workspace_id', $workspaceId)
            ->where($about)
            ->with('user')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }
}
