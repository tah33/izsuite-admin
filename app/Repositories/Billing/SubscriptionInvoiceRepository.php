<?php

namespace App\Repositories\Billing;

use App\Models\Billing\SubscriptionInvoice;
use App\Models\User\Workspace;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class SubscriptionInvoiceRepository
{
    public function create(Workspace $workspace, array $data): SubscriptionInvoice
    {
        return $workspace->subscriptionInvoices()->create($data);
    }

    public function update(SubscriptionInvoice $invoice, array $data): SubscriptionInvoice
    {
        $invoice->update($data);

        return $invoice;
    }

    /** One of the business's invoices - another business's is not found. */
    /** The invoice, its row locked until the transaction ends - for a payment to settle it once. */
    public function lock(int $id): ?SubscriptionInvoice
    {
        return SubscriptionInvoice::whereKey($id)->lockForUpdate()->first();
    }

    public function findFor(Workspace $workspace, int $id): ?SubscriptionInvoice
    {
        return $workspace->subscriptionInvoices()->find($id);
    }

    /** The invoice the business has still to pay, if any. */
    public function openFor(Workspace $workspace): ?SubscriptionInvoice
    {
        return $workspace->subscriptionInvoices()->where('status', SubscriptionInvoice::OPEN)->latest('id')->first();
    }

    /** The paid invoice whose period starts on $startsOn - what the current period was paid with. */
    public function paidFor(Workspace $workspace, string $startsOn): ?SubscriptionInvoice
    {
        return $workspace->subscriptionInvoices()
            ->where('status', SubscriptionInvoice::PAID)
            ->whereDate('period_starts_on', $startsOn)
            ->latest('id')
            ->first();
    }

    /** The renewal for the period starting on $startsOn - open or paid, never a cancelled one. */
    public function renewalFor(Workspace $workspace, string $startsOn): ?SubscriptionInvoice
    {
        return $workspace->subscriptionInvoices()
            ->where('kind', SubscriptionInvoice::RENEWAL)
            ->whereDate('period_starts_on', $startsOn)
            ->where('status', '!=', SubscriptionInvoice::VOID)
            ->latest('id')
            ->first();
    }

    /** The business's billing history, newest first. */
    public function pageFor(Workspace $workspace, int $perPage): LengthAwarePaginator
    {
        return $workspace->subscriptionInvoices()->latest('issued_on')->latest('id')->paginate($perPage);
    }

    /** Any business's invoice, with its business and owner - for the admin. */
    public function findOrFail(int $id): SubscriptionInvoice
    {
        return SubscriptionInvoice::with(['workspace.user', 'confirmer'])->findOrFail($id);
    }

    /**
     * Every business's invoices for the admin, the open ones first, then the
     * newest: narrowed to a status, and to a number, business or reference.
     */
    public function adminPage(?string $status, ?string $search, int $perPage): LengthAwarePaginator
    {
        return SubscriptionInvoice::with(['workspace:id,name,user_id', 'workspace.user:id,first_name,last_name,email', 'confirmer:id,first_name,last_name', 'unappliedPayments'])
            ->when($status, fn ($query) => $query->where('status', $status))
            ->when($search, fn ($query) => $query->where(fn ($query) => $query
                ->where('number', 'like', "%{$search}%")
                ->orWhere('receipt_number', 'like', "%{$search}%")
                ->orWhere('transfer_reference', 'like', "%{$search}%")
                ->orWhere('payment_reference', 'like', "%{$search}%")
                ->orWhereHas('workspace', fn ($query) => $query->where('name', 'like', "%{$search}%"))))
            ->orderByRaw('status = ? desc', [SubscriptionInvoice::OPEN])
            ->latest('issued_on')
            ->latest('id')
            ->paginate($perPage)
            ->withQueryString();
    }
}
