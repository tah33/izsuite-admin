<?php

namespace App\Repositories\User;

use App\Models\User\PaymentAccount;
use App\Models\User\Purchase;
use App\Models\User\SupplierPayment;
use App\Models\User\WithholdingTax;
use App\Models\User\Workspace;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Supplier payments belong to one business. Every lookup is scoped to it, so
 * another business's payment is simply not found.
 */
class SupplierPaymentRepository
{
    /** What a voucher's page shows with it. */
    private const SHOWN_WITH = ['purchase:id,number,currency,grand_total,balance_due', 'contact:id,name', 'account:id,name,currency', 'creator:id,first_name,last_name', 'reverser:id,first_name,last_name', 'sourceApp:id,name'];

    /**
     * Payments Made (Doc 09 section 4): newest first, searched by voucher
     * number, reference, supplier or purchase number, and filtered by status,
     * account, supplier - for a contact's profile - and dates.
     */
    public function paginateFor(Workspace $workspace, array $filters, int $perPage): LengthAwarePaginator
    {
        return SupplierPayment::query()
            ->where('workspace_id', $workspace->id)
            ->with(['purchase:id,number', 'contact:id,name', 'account:id,name,currency'])
            ->when($filters['search'] ?? null, fn (Builder $query, string $search) => $query->where(fn (Builder $query) => $query
                ->where('number', 'like', "%{$search}%")
                ->orWhere('reference', 'like', "%{$search}%")
                ->orWhereHas('contact', fn (Builder $query) => $query->where('name', 'like', "%{$search}%"))
                ->orWhereHas('purchase', fn (Builder $query) => $query->where('number', 'like', "%{$search}%"))))
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

    public function findFor(Workspace $workspace, int $id): ?SupplierPayment
    {
        return $workspace->supplierPayments()->with(self::SHOWN_WITH)->find($id);
    }

    /** Read again after a change, with what its page shows. */
    public function loadShown(SupplierPayment $payment): SupplierPayment
    {
        return $payment->load(self::SHOWN_WITH);
    }

    /** With what its voucher shows: its purchase whole - the business and supplier it kept - the supplier and the account. */
    public function loadForVoucher(SupplierPayment $payment): SupplierPayment
    {
        return $payment->load(['purchase.location:id,name', 'contact', 'account:id,name,currency']);
    }

    /** Whether one of the business's vouchers already has $number. */
    public function voucherNumberTaken(Workspace $workspace, string $number): bool
    {
        return $workspace->supplierPayments()->where('number', $number)->exists();
    }

    /** The completed payments of $purchase. */
    public function completedFor(Purchase $purchase): Collection
    {
        return $purchase->payments()
            ->where('status', SupplierPayment::COMPLETED)
            ->get(['id', 'purchase_id', 'amount', 'applied_amount', 'withholding_amount']);
    }

    /** Made through the business, so its workspace is never read from $data. */
    public function create(Workspace $workspace, array $data): SupplierPayment
    {
        return $workspace->supplierPayments()->create($data);
    }

    public function update(SupplierPayment $payment, array $data): SupplierPayment
    {
        $payment->update($data);

        return $payment;
    }

    /* ---------------------------------------------------------- what is in use */

    public function usesWithholdingTax(WithholdingTax $rate): bool
    {
        return SupplierPayment::where('withholding_tax_id', $rate->id)->exists();
    }

    public function usesAccount(PaymentAccount $account): bool
    {
        return SupplierPayment::where('payment_account_id', $account->id)->exists();
    }
}
