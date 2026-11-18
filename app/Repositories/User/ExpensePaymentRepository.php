<?php

namespace App\Repositories\User;

use App\Models\User\Expense;
use App\Models\User\ExpensePayment;
use App\Models\User\PaymentAccount;
use App\Models\User\WithholdingTax;
use App\Models\User\Workspace;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * The expense_payments table (Doc 10 sections 12 and 13). Every query is the
 * business's own.
 */
class ExpensePaymentRepository
{
    /** What a voucher's page shows with it - the expense's payee, whether a contact or a name typed in. */
    private const SHOWN_WITH = ['expense:id,number,currency,contact_id,payee_name,payee_snapshot', 'contact:id,name', 'account:id,name,currency', 'creator:id,first_name,last_name', 'reverser:id,first_name,last_name', 'sourceApp:id,name'];

    /**
     * Payments Made (Doc 10 section 3): newest first, searched by voucher
     * number, reference, payee or expense number, and filtered by status,
     * account, payee contact - for a contact's profile - and dates.
     */
    public function paginateFor(Workspace $workspace, array $filters, int $perPage): LengthAwarePaginator
    {
        return ExpensePayment::query()
            ->where('workspace_id', $workspace->id)
            ->with(['expense:id,number,contact_id,payee_name,payee_snapshot', 'contact:id,name', 'account:id,name,currency'])
            ->when($filters['search'] ?? null, fn (Builder $query, string $search) => $query->where(fn (Builder $query) => $query
                ->where('number', 'like', "%{$search}%")
                ->orWhere('reference', 'like', "%{$search}%")
                ->orWhereHas('contact', fn (Builder $query) => $query->where('name', 'like', "%{$search}%"))
                ->orWhereHas('expense', fn (Builder $query) => $query
                    ->where('number', 'like', "%{$search}%")
                    ->orWhere('payee_name', 'like', "%{$search}%"))))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['contact_id'] ?? null, fn (Builder $query, $id) => $query->where('contact_id', $id))
            ->atLocations($filters['location_ids'] ?? null)
            ->when($filters['payment_account_id'] ?? null, fn (Builder $query, $id) => $query->where('payment_account_id', $id))
            ->when($filters['date_from'] ?? null, fn (Builder $query, string $date) => $query->where('payment_date', '>=', $date))
            ->when($filters['date_to'] ?? null, fn (Builder $query, string $date) => $query->where('payment_date', '<=', $date))
            ->orderByDesc('payment_date')
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    public function findFor(Workspace $workspace, int $id): ?ExpensePayment
    {
        return $workspace->expensePayments()->with(self::SHOWN_WITH)->find($id);
    }

    /** Read again after a change, with what its page shows. */
    public function loadShown(ExpensePayment $payment): ExpensePayment
    {
        return $payment->load(self::SHOWN_WITH);
    }

    /** With what its voucher shows: its expense whole - the business and payee it kept - the payee contact and the account. */
    public function loadForVoucher(ExpensePayment $payment): ExpensePayment
    {
        return $payment->load(['expense.location:id,name', 'contact', 'account:id,name,currency']);
    }

    /** Whether one of the business's vouchers already has $number. */
    public function voucherNumberTaken(Workspace $workspace, string $number): bool
    {
        return $workspace->expensePayments()->where('number', $number)->exists();
    }

    /** The completed payments of $expense. */
    public function completedFor(Expense $expense): Collection
    {
        return $expense->payments()
            ->where('status', ExpensePayment::COMPLETED)
            ->get(['id', 'expense_id', 'amount', 'applied_amount', 'withholding_amount']);
    }

    /** Made through the business, so its workspace is never read from $data. */
    public function create(Workspace $workspace, array $data): ExpensePayment
    {
        return $workspace->expensePayments()->create($data);
    }

    public function update(ExpensePayment $payment, array $data): ExpensePayment
    {
        $payment->update($data);

        return $payment;
    }

    /* ---------------------------------------------------------- what is in use */

    public function usesWithholdingTax(WithholdingTax $rate): bool
    {
        return ExpensePayment::where('withholding_tax_id', $rate->id)->exists();
    }

    public function usesAccount(PaymentAccount $account): bool
    {
        return ExpensePayment::where('payment_account_id', $account->id)->exists();
    }
}
