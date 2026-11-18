<?php

namespace App\Repositories\Admin;

use App\Models\Billing\SubscriptionCredit;
use App\Models\Billing\SubscriptionPayment;
use App\Models\Billing\SubscriptionRefund;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * Admin > Payments and Admin > Refunds (Doc 15 section 14 - Phase C2a):
 * every payment for izSuite's invoices - a card's, the saved card's tries,
 * a transfer izSuite confirmed - with what of it is refunded, and every
 * refund with its payment and reason.
 */
class PaymentAdminRepository
{
    /** The payments list's status filter: paid, taken but not applied, failed (refused, or waiting for the cardholder), processing, refunded. */
    public const STATUSES = ['paid', 'not_applied', 'failed', 'processing', 'refunded'];

    /**
     * Newest first, each with its business and owner, its invoice, what of it
     * is refunded and whether a refund waits. $filters: status (STATUSES),
     * method (card or transfer) and search (the business's name or BW- ID,
     * the owner's e-mail, the invoice's number or the provider's reference).
     */
    public function page(array $filters, int $perPage): LengthAwarePaginator
    {
        return SubscriptionPayment::query()
            ->with(['workspace:id,name,user_id', 'workspace.user:id,first_name,last_name,email', 'invoice:id,number,package_name,cycle,kind'])
            ->withSum(['refunds as refunded' => fn (Builder $refund) => $refund->where('status', SubscriptionRefund::SUCCEEDED)], 'amount')
            ->withExists(['refunds as refund_pending' => fn (Builder $refund) => $refund->where('status', SubscriptionRefund::PENDING)])
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => match ($status) {
                'paid'        => $query->where('status', SubscriptionPayment::SUCCEEDED)->whereNull('note'),
                'not_applied' => $query->where('status', SubscriptionPayment::SUCCEEDED)->whereNotNull('note'),
                'failed'      => $query->whereIn('status', [SubscriptionPayment::FAILED, SubscriptionPayment::ACTION_REQUIRED]),
                'processing'  => $query->where('status', SubscriptionPayment::PROCESSING),
                'refunded'    => $query->whereHas('refunds', fn (Builder $refund) => $refund->where('status', SubscriptionRefund::SUCCEEDED)),
                default       => $query,
            })
            ->when(($filters['method'] ?? null) === 'card', fn (Builder $query) => $query->where('provider', '!=', SubscriptionPayment::TRANSFER))
            ->when(($filters['method'] ?? null) === 'transfer', fn (Builder $query) => $query->where('provider', SubscriptionPayment::TRANSFER))
            ->when($filters['search'] ?? null, fn (Builder $query, string $search) => $this->search($query, $search, fn (Builder $query, string $like) => $query
                ->orWhere('reference', 'like', $like)
                ->orWhereHas('invoice', fn (Builder $invoice) => $invoice->where('number', 'like', $like))))
            ->latest('id')
            ->paginate(requested_per_page($perPage))
            ->withQueryString();
    }

    /** Newest first, each with its business, invoice, payment and the admin who made it. $filters: status, and search as page() has it. */
    public function refundsPage(array $filters, int $perPage): LengthAwarePaginator
    {
        return SubscriptionRefund::query()
            ->with(['workspace:id,name,user_id', 'workspace.user:id,first_name,last_name,email', 'invoice:id,number', 'payment:id,provider,card,reference', 'refunder:id,first_name,last_name'])
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['search'] ?? null, fn (Builder $query, string $search) => $this->search($query, $search, fn (Builder $query, string $like) => $query
                ->orWhere('reference', 'like', $like)
                ->orWhere('transfer_reference', 'like', $like)
                ->orWhereHas('invoice', fn (Builder $invoice) => $invoice->where('number', 'like', $like))))
            ->latest('id')
            ->paginate(requested_per_page($perPage))
            ->withQueryString();
    }

    /**
     * Admin > Credits (Doc 15 Phase C2b): every movement of izSuite's credit,
     * newest first, with its business, invoice and admin. $filters: kind, and
     * search as page() has it.
     */
    public function creditsPage(array $filters, int $perPage): LengthAwarePaginator
    {
        return SubscriptionCredit::query()
            ->with(['workspace:id,name,user_id', 'workspace.user:id,first_name,last_name,email', 'invoice:id,number', 'creator:id,first_name,last_name'])
            ->when($filters['kind'] ?? null, fn (Builder $query, string $kind) => $query->where('kind', $kind))
            ->when($filters['search'] ?? null, fn (Builder $query, string $search) => $this->search($query, $search, fn (Builder $query, string $like) => $query
                ->orWhereHas('invoice', fn (Builder $invoice) => $invoice->where('number', 'like', $like))))
            ->latest('id')
            ->paginate(requested_per_page($perPage))
            ->withQueryString();
    }

    /** One payment, with its business and invoice - null when there is none. */
    public function find(int $id): ?SubscriptionPayment
    {
        return SubscriptionPayment::with(['workspace.user', 'invoice'])->find($id);
    }

    /** The business's name or BW- ID, or its owner's e-mail - and what $more adds for the record itself. */
    private function search(Builder $query, string $search, callable $more): Builder
    {
        $like = '%'.addcslashes($search, '%_\\').'%';
        $id   = preg_match('/^(BW-?)?0*(\d+)$/i', $search, $match) ? (int) $match[2] : 0;

        return $query->where(function (Builder $query) use ($like, $id, $more) {
            $query->whereHas('workspace', fn (Builder $business) => $business->where('name', 'like', $like)
                ->orWhereHas('user', fn (Builder $owner) => $owner->where('email', 'like', $like)))
                ->when($id > 0, fn (Builder $query) => $query->orWhere('workspace_id', $id));

            $more($query, $like);
        });
    }
}
