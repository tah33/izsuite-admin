<?php

namespace App\Repositories\User;

use App\Models\User\CatalogProduct;
use App\Models\User\EcommerceDiscount;
use App\Models\User\EcommerceOrder;
use App\Models\User\EcommerceOrderPromotion;
use App\Models\User\Workspace;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * A business's discounts (Doc 19 section 19.31 - Phases H1 and H2a): a page
 * of them with their uses, one by id or code, and the automatic ones on a
 * day - locked, as an order is placed - made, changed and deleted with the
 * products they are for; their uses, in all and by one e-mail; and the
 * listings a discount may be for. A use is an order that took it, not
 * cancelled.
 */
class EcommerceDiscountRepository
{
    /** A page of $workspace's discounts, the newest first, searched by name or code, each with its products and uses. */
    public function paginateFor(Workspace $workspace, ?string $search, int $perPage): LengthAwarePaginator
    {
        return EcommerceDiscount::query()
            ->where('workspace_id', $workspace->id)
            ->when($search, fn ($query, $search) => $query->where(fn ($query) => $query->where('name', 'like', "%{$search}%")->orWhere('code', 'like', '%'.mb_strtoupper($search).'%')))
            ->with('products:id,title')
            ->withCount(['promotions as uses' => fn (Builder $query) => $this->notCancelled($query)])
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    /** $workspace's discount $id, with its products and uses - or none. */
    public function findFor(Workspace $workspace, int $id): ?EcommerceDiscount
    {
        return EcommerceDiscount::query()
            ->where('workspace_id', $workspace->id)
            ->with('products:id,title')
            ->withCount(['promotions as uses' => fn (Builder $query) => $this->notCancelled($query)])
            ->find($id);
    }

    /** $workspace's discount with $code (in capitals) - locked when $lock, inside a transaction - with its products. */
    public function findByCode(Workspace $workspace, string $code, bool $lock = false): ?EcommerceDiscount
    {
        return EcommerceDiscount::query()
            ->where('workspace_id', $workspace->id)
            ->where('code', $code)
            ->when($lock, fn ($query) => $query->lockForUpdate())
            ->with('products:id')
            ->first();
    }

    /**
     * Phase H2a: $workspace's automatic discounts that are on, on $today (the
     * business's) - locked when $lock, inside a transaction - with their
     * products.
     *
     * @return Collection<int, EcommerceDiscount>
     */
    public function automaticFor(Workspace $workspace, string $today, bool $lock = false): Collection
    {
        return EcommerceDiscount::query()
            ->where('workspace_id', $workspace->id)
            ->whereNull('code')
            ->where('is_active', true)
            ->where(fn ($query) => $query->whereNull('starts_on')->orWhere('starts_on', '<=', $today))
            ->where(fn ($query) => $query->whereNull('ends_on')->orWhere('ends_on', '>=', $today))
            ->when($lock, fn ($query) => $query->lockForUpdate())
            ->with('products:id')
            ->orderBy('id')
            ->get();
    }

    /** Whether another of $workspace's discounts has $code. */
    public function codeTaken(Workspace $workspace, string $code, ?int $except = null): bool
    {
        return EcommerceDiscount::query()->where('workspace_id', $workspace->id)->where('code', $code)->when($except, fn ($query) => $query->whereKeyNot($except))->exists();
    }

    /**
     * The uses of the discounts $ids - in all, and by $email - keyed by id:
     * those missing have none. Read with a lock while an order is placed
     * ($lock), after the discounts were locked, so a use another order took
     * a moment before is counted.
     *
     * @param  list<int>  $ids
     * @return array<int, array{all: int, customer: int}>
     */
    public function usesOf(array $ids, ?string $email, bool $lock = false): array
    {
        if ($ids === []) {
            return [];
        }

        return EcommerceOrderPromotion::query()
            ->join('ecommerce_orders', 'ecommerce_orders.id', '=', 'ecommerce_order_promotions.ecommerce_order_id')
            ->whereIn('ecommerce_order_promotions.ecommerce_discount_id', $ids)
            ->where('ecommerce_orders.status', '!=', EcommerceOrder::CANCELLED)
            ->groupBy('ecommerce_order_promotions.ecommerce_discount_id')
            ->selectRaw('ecommerce_order_promotions.ecommerce_discount_id as discount_id, count(*) as uses, sum(case when ecommerce_orders.customer_email = ? then 1 else 0 end) as customer_uses', [mb_strtolower((string) $email)])
            ->when($lock, fn ($query) => $query->sharedLock())
            ->toBase()
            ->get()
            ->mapWithKeys(fn (object $row) => [(int) $row->discount_id => ['all' => (int) $row->uses, 'customer' => (int) $row->customer_uses]])
            ->all();
    }

    /** Whether $discount was ever taken by an order, cancelled or not. */
    public function everUsed(EcommerceDiscount $discount): bool
    {
        return $discount->promotions()->exists();
    }

    /** Made through the business, so its workspace is never read from $data; with the products it is for. */
    public function create(Workspace $workspace, array $data, array $productIds): EcommerceDiscount
    {
        $discount = new EcommerceDiscount($data);
        $discount->workspace_id = $workspace->id;
        $discount->save();
        $discount->products()->sync($productIds);

        return $discount;
    }

    public function update(EcommerceDiscount $discount, array $data, array $productIds): void
    {
        $discount->update($data);
        $discount->products()->sync($productIds);
    }

    public function delete(EcommerceDiscount $discount): void
    {
        $discount->delete();
    }

    /** Of $ids, those that are $workspace's listings. */
    public function productIdsOf(Workspace $workspace, array $ids): array
    {
        return CatalogProduct::query()->where('workspace_id', $workspace->id)->whereIn('id', $ids)->pluck('id')->all();
    }

    /** $workspace's listings, by title - those matching $search - for a discount to be for: up to 20, each id and title. */
    public function productChoices(Workspace $workspace, ?string $search): Collection
    {
        return CatalogProduct::query()
            ->where('workspace_id', $workspace->id)
            ->when($search, fn ($query, $search) => $query->where('title', 'like', "%{$search}%"))
            ->orderBy('title')
            ->limit(20)
            ->get(['id', 'title']);
    }

    /** Only the uses whose order is not cancelled. */
    private function notCancelled(Builder $query): Builder
    {
        return $query->whereHas('order', fn (Builder $query) => $query->where('status', '!=', EcommerceOrder::CANCELLED));
    }
}
