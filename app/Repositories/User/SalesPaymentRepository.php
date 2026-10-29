<?php

namespace App\Repositories\User;

use App\Models\User\PaymentAccount;
use App\Models\User\SalesDocument;
use App\Models\User\SalesPayment;
use App\Models\User\WithholdingTax;
use App\Models\User\Workspace;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Customer payments and refunds belong to one business. Every lookup is
 * scoped to it, so another business's payment is simply not found.
 */
class SalesPaymentRepository
{
    /** What a receipt's page shows with it. */
    private const SHOWN_WITH = ['document:id,type,number,currency,grand_total,balance_due,source_id', 'contact:id,name', 'account:id,name,currency', 'creator:id,first_name,last_name', 'reverser:id,first_name,last_name', 'emails.sender:id,first_name,last_name'];

    /**
     * Payments Received (Doc 08 section 2): payments only unless $filters
     * kind says refunds or all, newest first, searched and filtered - by
     * contact_id for a contact's profile (Doc 03 section 11).
     */
    public function paginatePaymentsFor(Workspace $workspace, array $filters, int $perPage): LengthAwarePaginator
    {
        $kind = $filters['kind'] ?? SalesPayment::PAYMENT;

        return SalesPayment::query()
            ->where('workspace_id', $workspace->id)
            ->when($kind !== 'all', fn (Builder $query) => $query->where('kind', $kind))
            ->when($filters['contact_id'] ?? null, fn (Builder $query, $id) => $query->where('contact_id', $id))
            ->with(['document:id,number', 'contact:id,name', 'account:id,name,currency'])
            ->when($filters['search'] ?? null, fn (Builder $query, string $search) => $query->where(fn (Builder $query) => $query
                ->where('number', 'like', "%{$search}%")
                ->orWhere('reference', 'like', "%{$search}%")
                ->orWhereHas('contact', fn (Builder $query) => $query->where('name', 'like', "%{$search}%"))
                ->orWhereHas('document', fn (Builder $query) => $query->where('number', 'like', "%{$search}%"))))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['payment_account_id'] ?? null, fn (Builder $query, $id) => $query->where('payment_account_id', $id))
            ->when($filters['date_from'] ?? null, fn (Builder $query, string $date) => $query->where('payment_date', '>=', $date))
            ->when($filters['date_to'] ?? null, fn (Builder $query, string $date) => $query->where('payment_date', '<=', $date))
            ->orderByDesc('payment_date')
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    public function findFor(Workspace $workspace, int $id): ?SalesPayment
    {
        return $workspace->salesPayments()->with(self::SHOWN_WITH)->find($id);
    }

    /** Read again after a change, with what its page shows. */
    public function loadShown(SalesPayment $payment): SalesPayment
    {
        return $payment->load(self::SHOWN_WITH);
    }

    /** With what its receipt shows: its invoice whole - the business and customer it kept - the customer and the account. */
    public function loadForReceipt(SalesPayment $payment): SalesPayment
    {
        return $payment->load(['document.location:id,name', 'contact', 'account:id,name,currency']);
    }

    /** Whether one of the business's receipts already has $number. */
    public function receiptNumberTaken(Workspace $workspace, string $number): bool
    {
        return $workspace->salesPayments()->where('kind', SalesPayment::PAYMENT)->where('number', $number)->exists();
    }

    /** A document's payments or refunds, oldest first, with the account each went through. */
    public function forDocument(SalesDocument $document): Collection
    {
        return $document->payments()->with('account:id,name,currency')->get();
    }

    /** The completed ones of the documents $ids: payments of invoices, refunds of credit notes. */
    public function completedFor(array $documentIds): Collection
    {
        return SalesPayment::query()
            ->whereIn('sales_document_id', $documentIds)
            ->where('status', SalesPayment::COMPLETED)
            ->get(['id', 'kind', 'sales_document_id', 'amount', 'applied_amount', 'withholding_amount']);
    }

    /** Made through the business, so its workspace is never read from $data. */
    public function create(Workspace $workspace, array $data): SalesPayment
    {
        return $workspace->salesPayments()->create($data);
    }

    public function update(SalesPayment $payment, array $data): SalesPayment
    {
        $payment->update($data);

        return $payment;
    }

    /* ---------------------------------------------------------- what is in use */

    public function usesWithholdingTax(WithholdingTax $rate): bool
    {
        return SalesPayment::where('withholding_tax_id', $rate->id)->exists();
    }

    public function usesAccount(PaymentAccount $account): bool
    {
        return SalesPayment::where('payment_account_id', $account->id)->exists();
    }
}
