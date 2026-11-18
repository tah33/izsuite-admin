<?php

namespace App\Repositories\User;

use App\Enums\ExpenseStatus;
use App\Models\User\Contact;
use App\Models\User\Expense;
use App\Models\User\ExpenseCategory;
use App\Models\User\ExpenseLine;
use App\Models\User\Location;
use App\Models\User\TaxRate;
use App\Models\User\Workspace;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\LazyCollection;

/**
 * The expenses table and its lines (Doc 10). Every query is the business's
 * own. The list is searched by number, receipt number, payee, description,
 * category or reference, and filtered by status, payment - overdue among it -
 * payee, category, location, currency and dates (section 8), newest first.
 */
class ExpenseRepository
{
    /** What an expense's page and edit form show with it - its payments with the account each came out of. */
    private const SHOWN_WITH = ['lines', 'contact', 'location:id,name', 'payments.account:id,name,currency', 'sourceApp:id,name', 'creator:id,first_name,last_name'];

    public function paginateFor(Workspace $workspace, array $filters, int $perPage, string $today): LengthAwarePaginator
    {
        return $this->query($workspace, $filters, $today)->paginate($perPage);
    }

    /** Everything the filters let through, read a few hundred at a time - for an export. */
    public function lazyFor(Workspace $workspace, array $filters, string $today): LazyCollection
    {
        return $this->query($workspace, $filters, $today)->lazy(500);
    }

    public function findFor(Workspace $workspace, int $id): ?Expense
    {
        return $workspace->expenses()->with(self::SHOWN_WITH)->find($id);
    }

    /** Read again after a save, with what its page shows. */
    public function loadShown(Expense $expense): Expense
    {
        return $expense->load(self::SHOWN_WITH);
    }

    /** Its lines with their categories and the rates their taxes came from, as those are now. */
    public function loadLineReferences(Expense $expense): Expense
    {
        return $expense->load('lines.category', 'lines.taxRate');
    }

    /** Whether one of the business's expenses already has $number. */
    public function numberTaken(Workspace $workspace, string $number): bool
    {
        return $workspace->expenses()->where('number', $number)->exists();
    }

    /**
     * Receipts that may have been recorded before (Doc 10 section 11): the
     * business's other recorded expenses for the same payee - the same contact,
     * or the same name typed in - with the same receipt number, or on the same
     * day for the same amount in the same currency. Oldest first.
     */
    public function duplicatesOf(Expense $expense): Collection
    {
        return Expense::query()
            ->where('workspace_id', $expense->workspace_id)
            ->where('id', '!=', $expense->id)
            ->where('status', ExpenseStatus::Recorded->value)
            ->when(
                $expense->contact_id,
                fn (Builder $query) => $query->where('contact_id', $expense->contact_id),
                fn (Builder $query) => $query->whereNull('contact_id')->whereRaw('LOWER(payee_name) = ?', [mb_strtolower((string) $expense->payee_name)]),
            )
            ->where(fn (Builder $query) => $query
                ->when($expense->receipt_number, fn (Builder $query) => $query->where('receipt_number', $expense->receipt_number))
                ->orWhere(fn (Builder $query) => $query
                    ->where('document_date', $expense->document_date)
                    ->where('currency', $expense->currency)
                    ->where('grand_total', $expense->grand_total)))
            ->orderBy('id')
            ->get(['id', 'number', 'document_date', 'receipt_number', 'currency', 'grand_total']);
    }

    /** Made through the business, so its workspace is never read from $data. */
    public function create(Workspace $workspace, array $data): Expense
    {
        return $workspace->expenses()->create($data);
    }

    public function update(Expense $expense, array $data): Expense
    {
        $expense->update($data);

        return $expense;
    }

    /** Its lines become $rows, in their order - and it counts as changed, for anyone saving over it (Doc 14 section 22). */
    public function replaceLines(Expense $expense, array $rows): void
    {
        $expense->lines()->delete();
        $expense->lines()->createMany($rows);
        $expense->unsetRelation('lines');
        $expense->touch();
    }

    public function delete(Expense $expense): void
    {
        $expense->delete();
    }

    /* ---------------------------------------------------------- what is in use */

    public function usesContact(Contact $contact): bool
    {
        return Expense::where('contact_id', $contact->id)->exists();
    }

    public function usesCategory(ExpenseCategory $category): bool
    {
        return ExpenseLine::where('expense_category_id', $category->id)->exists();
    }

    public function usesTaxRate(TaxRate $rate): bool
    {
        return ExpenseLine::where('tax_rate_id', $rate->id)->exists();
    }

    public function usesLocation(Location $location): bool
    {
        return Expense::where('location_id', $location->id)->exists();
    }

    /** Whether the business has recorded any expense: a transaction. */
    public function hasTransactions(Workspace $workspace): bool
    {
        return $workspace->expenses()->whereNotNull('number')->exists();
    }

    private function query(Workspace $workspace, array $filters, string $today): Builder
    {
        return Expense::query()
            ->where('workspace_id', $workspace->id)
            ->with(['contact:id,name', 'location:id,name', 'lines:id,expense_id,position,category_name'])
            ->when($filters['search'] ?? null, fn (Builder $query, string $search) => $query->where(fn (Builder $query) => $query
                ->where('number', 'like', "%{$search}%")
                ->orWhere('receipt_number', 'like', "%{$search}%")
                ->orWhere('reference', 'like', "%{$search}%")
                ->orWhere('payee_name', 'like', "%{$search}%")
                ->orWhereHas('contact', fn (Builder $query) => $query->where('name', 'like', "%{$search}%"))
                ->orWhereHas('lines', fn (Builder $query) => $query
                    ->where('description', 'like', "%{$search}%")
                    ->orWhere('category_name', 'like', "%{$search}%"))))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['payment_status'] ?? null, fn (Builder $query, string $status) => $status === 'outstanding'
                ? $query->where('status', ExpenseStatus::Recorded->value)->where('balance_due', '>', 0)
                : $query->where('payment_status', $status))
            ->when(filter_var($filters['overdue'] ?? false, FILTER_VALIDATE_BOOLEAN), fn (Builder $query) => $query
                ->where('status', ExpenseStatus::Recorded->value)
                ->where('due_date', '<', $today)
                ->where('balance_due', '>', 0))
            ->when($filters['contact_id'] ?? null, fn (Builder $query, $id) => $query->where('contact_id', $id))
            ->when($filters['category_id'] ?? null, fn (Builder $query, $id) => $query->whereHas('lines', fn (Builder $query) => $query->where('expense_category_id', $id)))
            ->when($filters['location_id'] ?? null, fn (Builder $query, $id) => $query->where('location_id', $id))
            ->atLocations($filters['location_ids'] ?? null)
            ->when($filters['currency'] ?? null, fn (Builder $query, string $currency) => $query->where('currency', $currency))
            ->when($filters['date_from'] ?? null, fn (Builder $query, string $date) => $query->where('document_date', '>=', $date))
            ->when($filters['date_to'] ?? null, fn (Builder $query, string $date) => $query->where('document_date', '<=', $date))
            ->orderByDesc('document_date')
            ->orderByDesc('id');
    }
}
