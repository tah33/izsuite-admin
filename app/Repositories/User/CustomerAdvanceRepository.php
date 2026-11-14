<?php

namespace App\Repositories\User;

use App\Models\User\CustomerAdvance;
use App\Models\User\SalesDocument;
use App\Models\User\Workspace;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Customer advances (Doc 16 Phase D1) belong to one business: every lookup is
 * scoped to it, so another business's advance is simply not found.
 */
class CustomerAdvanceRepository
{
    /** What an advance's page shows with it. */
    private const SHOWN_WITH = [
        'contact:id,name', 'document:id,type,number', 'payment:id,number', 'account:id,name,currency', 'location:id,name',
        'allocations.invoice:id,number', 'allocations.creator:id,first_name,last_name', 'allocations.remover:id,first_name,last_name',
        'creator:id,first_name,last_name', 'reverser:id,first_name,last_name',
    ];

    /**
     * Payments Received's Advances, newest first: searched by number,
     * reference or customer, and filtered - open (something left to apply),
     * applied or reversed; by contact_id for a customer's profile. One taken
     * at no location (from the customer) shows wherever its customer does.
     */
    public function paginateFor(Workspace $workspace, array $filters, int $perPage): LengthAwarePaginator
    {
        $locations = $filters['location_ids'] ?? null;

        return CustomerAdvance::query()
            ->where('workspace_id', $workspace->id)
            ->when($locations !== null, fn (Builder $query) => $query->where(fn (Builder $query) => $query->whereNull('location_id')->orWhereIn('location_id', $locations)))
            ->when($filters['contact_id'] ?? null, fn (Builder $query, $id) => $query->where('contact_id', $id))
            ->with(['contact:id,name', 'account:id,name,currency', 'document:id,type,number'])
            ->when($filters['search'] ?? null, fn (Builder $query, string $search) => $query->where(fn (Builder $query) => $query
                ->where('number', 'like', "%{$search}%")
                ->orWhere('reference', 'like', "%{$search}%")
                ->orWhereHas('contact', fn (Builder $query) => $query->where('name', 'like', "%{$search}%"))))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => match ($status) {
                'open'     => $query->where('status', CustomerAdvance::COMPLETED)->where('balance', '>', 0),
                'applied'  => $query->where('status', CustomerAdvance::COMPLETED)->where('balance', '<=', 0),
                default    => $query->where('status', $status),
            })
            ->when($filters['date_from'] ?? null, fn (Builder $query, string $date) => $query->where('payment_date', '>=', $date))
            ->when($filters['date_to'] ?? null, fn (Builder $query, string $date) => $query->where('payment_date', '<=', $date))
            ->orderByDesc('payment_date')
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    public function findFor(Workspace $workspace, int $id): ?CustomerAdvance
    {
        return $workspace->customerAdvances()->with(self::SHOWN_WITH)->find($id);
    }

    /** Read again after a change, with what its page shows. */
    public function loadShown(CustomerAdvance $advance): CustomerAdvance
    {
        return $advance->load(self::SHOWN_WITH);
    }

    public function create(Workspace $workspace, array $data): CustomerAdvance
    {
        return $workspace->customerAdvances()->create($data);
    }

    public function update(CustomerAdvance $advance, array $data): CustomerAdvance
    {
        $advance->update($data);

        return $advance;
    }

    public function numberTaken(Workspace $workspace, string $number): bool
    {
        return $workspace->customerAdvances()->where('number', $number)->exists();
    }

    /** A customer's advances with something left to apply in $currency, oldest first: what an invoice can take. */
    public function openFor(Workspace $workspace, int $contactId, string $currency): Collection
    {
        return $workspace->customerAdvances()
            ->where('contact_id', $contactId)
            ->where('currency', $currency)
            ->where('status', CustomerAdvance::COMPLETED)
            ->where('balance', '>', 0)
            ->orderBy('payment_date')
            ->orderBy('id')
            ->get(['id', 'number', 'payment_date', 'currency', 'balance']);
    }

    /** The advances paid for a sales order, or kept from a payment over an invoice, oldest first. */
    public function forDocument(SalesDocument $document): Collection
    {
        return CustomerAdvance::query()
            ->where('sales_document_id', $document->id)
            ->orderBy('id')
            ->get(['id', 'number', 'status', 'payment_date', 'currency', 'amount', 'balance']);
    }
}
