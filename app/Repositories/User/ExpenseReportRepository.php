<?php

namespace App\Repositories\User;

use App\Enums\ExpenseStatus;
use App\Models\User\Expense;
use App\Models\User\ExpenseLine;
use App\Models\User\ExpensePayment;
use App\Models\User\Workspace;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * What Basic Reports and the Dashboard read of Expenses Lite (Doc 07, Doc 02,
 * Doc 10 sections 17 and 18): recorded expenses by their own dates - each
 * with its lines, whose categories and taxes the reports need - and
 * completed expense payments by theirs. Never a draft or void expense, nor a
 * reversed payment (Doc 10 section 20). Every query is the business's own.
 *
 * $filters narrow them: location_id, contact_id - the payee, when one of the
 * business's contacts - currency - an expense's own, or a payment's
 * account's - payment_account_id for money out of one account, and
 * expense_category_id for expenses with a line in that category.
 */
class ExpenseReportRepository
{
    private const FIGURES = [
        'id', 'number', 'status', 'document_date', 'due_date', 'contact_id', 'payee_name', 'payee_snapshot', 'location_id', 'currency', 'exchange_rate',
        'price_tax_mode', 'receipt_number', 'subtotal', 'tax_total', 'grand_total', 'net_total', 'base_grand_total', 'base_tax_total', 'base_net_total',
        'taxes', 'amount_paid', 'withholding_amount', 'balance_due', 'payment_status',
    ];

    /** A line's category - as it kept it, and the one it is in - its description, its amount and its tax as kept. */
    private const LINES = 'lines:id,expense_id,position,expense_category_id,category_name,description,amount,tax_rate_id,tax_name,tax_rate,tax_treatment,tax_recoverability,tax_amount';

    /**
     * Recorded expenses dated from $from to $to, oldest first, with their
     * lines - and, given $asOf, what had paid each by then (paid_by,
     * withheld_by): the parts of ExpenseSettlement's balance, as of that day.
     */
    public function recorded(Workspace $workspace, string $from, string $to, array $filters = [], ?string $asOf = null): Collection
    {
        return $this->expenses($workspace, $filters, 'expenses.')
            ->select(array_map(fn (string $column) => "expenses.{$column}", self::FIGURES))
            ->when($asOf, fn (Builder $query, string $asOf) => $query
                ->selectRaw('COALESCE(p.paid_sum, 0) as paid_by, COALESCE(p.withheld_sum, 0) as withheld_by')
                ->leftJoinSub($this->paidBy($workspace, $asOf), 'p', 'p.expense_id', '=', 'expenses.id'))
            ->whereBetween('expenses.document_date', [$from, $to])
            ->with(['contact:id,name', 'location:id,name', self::LINES, 'lines.category:id,name'])
            ->orderBy('expenses.document_date')
            ->orderBy('expenses.id')
            ->get();
    }

    /** Whether any recorded expense has a taxed line - zero-rated and exempt ones too: the Tax Summary's history. */
    public function hasTaxHistory(Workspace $workspace): bool
    {
        return ExpenseLine::query()
            ->whereNotNull('tax_name')
            ->whereHas('expense', fn (Builder $query) => $query->where('workspace_id', $workspace->id)->where('status', ExpenseStatus::Recorded->value))
            ->exists();
    }

    /** Whether any completed expense payment had tax withheld - the history that keeps Tax Withheld showing. */
    public function hasWithholdingHistory(Workspace $workspace): bool
    {
        return ExpensePayment::query()
            ->where('workspace_id', $workspace->id)
            ->where('status', ExpensePayment::COMPLETED)
            ->where('withholding_amount', '>', 0)
            ->exists();
    }

    /** Completed expense payments dated from $from to $to, oldest first: money that went out to payees. */
    public function paymentsMade(Workspace $workspace, string $from, string $to, array $filters = []): Collection
    {
        return ExpensePayment::query()
            ->where('workspace_id', $workspace->id)
            ->where('status', ExpensePayment::COMPLETED)
            ->whereBetween('payment_date', [$from, $to])
            ->when($filters['location_id'] ?? null, fn (Builder $query, $id) => $query->where('location_id', $id))
            ->when($filters['contact_id'] ?? null, fn (Builder $query, $id) => $query->where('contact_id', $id))
            ->when($filters['payment_account_id'] ?? null, fn (Builder $query, $id) => $query->where('payment_account_id', $id))
            ->when($filters['currency'] ?? null, fn (Builder $query, string $currency) => $query->whereHas('account', fn (Builder $query) => $query->where('currency', $currency)))
            ->when($filters['document_currency'] ?? null, fn (Builder $query, string $currency) => $query->whereHas('expense', fn (Builder $query) => $query->where('currency', $currency)))
            ->with([
                'expense:id,number,currency,exchange_rate,contact_id,payee_name,payee_snapshot', 'expense.contact:id,name',
                'account:id,name,currency', 'location:id,name',
            ])
            ->orderBy('payment_date')
            ->orderBy('id')
            ->get();
    }

    /** The newest expenses, drafts and void ones too - the Dashboard's Recent Transactions. */
    public function latestExpenses(Workspace $workspace, int $limit, ?int $locationId): Collection
    {
        return Expense::query()
            ->where('workspace_id', $workspace->id)
            ->when($locationId, fn (Builder $query, int $id) => $query->where('location_id', $id))
            ->with('contact:id,name')
            ->orderByDesc('document_date')
            ->orderByDesc('id')
            ->limit($limit)
            ->get(['id', 'number', 'status', 'document_date', 'contact_id', 'payee_name', 'payee_snapshot', 'currency', 'grand_total', 'created_at']);
    }

    /** The newest expense payments, reversed ones too, with who was paid. */
    public function latestPayments(Workspace $workspace, int $limit, ?int $locationId): Collection
    {
        return ExpensePayment::query()
            ->where('workspace_id', $workspace->id)
            ->when($locationId, fn (Builder $query, int $id) => $query->where('location_id', $id))
            ->with(['expense:id,contact_id,payee_name,payee_snapshot', 'expense.contact:id,name', 'account:id,currency'])
            ->orderByDesc('payment_date')
            ->orderByDesc('id')
            ->limit($limit)
            ->get(['id', 'number', 'status', 'payment_date', 'expense_id', 'payment_account_id', 'amount', 'created_at']);
    }

    /** What completed payments had paid each expense by $asOf, and the tax withheld from them. */
    private function paidBy(Workspace $workspace, string $asOf): Builder
    {
        return ExpensePayment::query()
            ->selectRaw('expense_id, SUM(applied_amount) as paid_sum, SUM(withholding_amount) as withheld_sum')
            ->where('workspace_id', $workspace->id)
            ->where('status', ExpensePayment::COMPLETED)
            ->where('payment_date', '<=', $asOf)
            ->groupBy('expense_id');
    }

    /** Recorded expenses with $filters - columns prefixed with $table where the query joins. */
    private function expenses(Workspace $workspace, array $filters, string $table = ''): Builder
    {
        return Expense::query()
            ->where("{$table}workspace_id", $workspace->id)
            ->where("{$table}status", ExpenseStatus::Recorded->value)
            ->when($filters['location_id'] ?? null, fn (Builder $query, $id) => $query->where("{$table}location_id", $id))
            ->when($filters['contact_id'] ?? null, fn (Builder $query, $id) => $query->where("{$table}contact_id", $id))
            ->when($filters['currency'] ?? null, fn (Builder $query, string $currency) => $query->where("{$table}currency", $currency))
            ->when($filters['expense_category_id'] ?? null, fn (Builder $query, $id) => $query->whereHas('lines', fn (Builder $query) => $query->where('expense_category_id', $id)));
    }
}
