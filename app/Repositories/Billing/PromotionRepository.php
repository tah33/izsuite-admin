<?php

namespace App\Repositories\Billing;

use App\Models\Billing\Promotion;
use App\Models\Billing\SubscriptionInvoice;
use App\Models\User\Workspace;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/** Promotion codes (Doc 15 Phase C3b), and how often each was taken. */
class PromotionRepository
{
    /** The promotion with $code - in capitals - locked to the end of the transaction when $lock, while an invoice takes it. */
    public function findByCode(string $code, bool $lock = false): ?Promotion
    {
        return Promotion::where('code', $code)->when($lock, fn (Builder $query) => $query->lockForUpdate())->first();
    }

    public function find(int $id): ?Promotion
    {
        return Promotion::find($id);
    }

    /** The times $promotion was taken: its invoices not cancelled - $workspace's alone, when given. */
    public function uses(Promotion $promotion, ?Workspace $workspace = null): int
    {
        return $promotion->invoices()
            ->where('status', '!=', SubscriptionInvoice::VOID)
            ->when($workspace, fn (Builder $query) => $query->where('workspace_id', $workspace->id))
            ->count();
    }

    /** Admin > Promotions: newest first, each with how often it was taken. $filters: search (name or code). */
    public function page(array $filters, int $perPage): LengthAwarePaginator
    {
        return Promotion::query()
            ->withCount(['invoices as uses' => fn (Builder $invoice) => $invoice->where('status', '!=', SubscriptionInvoice::VOID)])
            ->when($filters['search'] ?? null, fn (Builder $query, string $search) => $query->where(fn (Builder $query) => $query
                ->where('name', 'like', '%'.addcslashes($search, '%_\\').'%')
                ->orWhere('code', 'like', '%'.addcslashes(strtoupper($search), '%_\\').'%')))
            ->latest('id')
            ->paginate(requested_per_page($perPage))
            ->withQueryString();
    }

    public function create(array $data): Promotion
    {
        return Promotion::create($data);
    }

    public function update(Promotion $promotion, array $data): Promotion
    {
        $promotion->update($data);

        return $promotion;
    }

    public function delete(Promotion $promotion): void
    {
        $promotion->delete();
    }
}
