<?php

namespace App\Repositories\User;

use App\Models\User\CatalogProduct;
use App\Models\User\Contact;
use App\Models\User\EcommerceDiscount;
use App\Models\User\EcommerceOrder;
use App\Models\User\EcommerceOrderPromotion;
use App\Models\User\ItemCategory;
use App\Models\User\Workspace;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * A business's discounts (Doc 19 section 19.31 - Phases H1 to H2b): a page
 * of them with their uses, one by id or code, and the automatic ones on a
 * day - locked, as an order is placed - made, changed and deleted with the
 * products, categories and contacts they are for; their uses, in all and by
 * one e-mail; and the listings, categories and customers a discount may be
 * for. A use is an order that took it, not cancelled.
 */
class EcommerceDiscountRepository
{
    /** What the Discounts page shows of what a discount is for, and whom: names. */
    private const SHOWN = ['products:id,title', 'categories:id,name', 'contacts:id,name'];

    /** What checkout weighs of the same: ids. */
    private const WEIGHED = ['products:id', 'categories:id', 'contacts:id'];

    /** A page of $workspace's discounts, the newest first, searched by name or code, each with what and whom it is for, and its uses. */
    public function paginateFor(Workspace $workspace, ?string $search, int $perPage): LengthAwarePaginator
    {
        return EcommerceDiscount::query()
            ->where('workspace_id', $workspace->id)
            ->when($search, fn ($query, $search) => $query->where(fn ($query) => $query->where('name', 'like', "%{$search}%")->orWhere('code', 'like', '%'.mb_strtoupper($search).'%')))
            ->with(self::SHOWN)
            ->withCount(['promotions as uses' => fn (Builder $query) => $this->notCancelled($query)])
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    /** $workspace's discount $id, with what and whom it is for, and its uses - or none. */
    public function findFor(Workspace $workspace, int $id): ?EcommerceDiscount
    {
        return EcommerceDiscount::query()
            ->where('workspace_id', $workspace->id)
            ->with(self::SHOWN)
            ->withCount(['promotions as uses' => fn (Builder $query) => $this->notCancelled($query)])
            ->find($id);
    }

    /** $workspace's discount with $code (in capitals) - locked when $lock, inside a transaction - with what and whom it is for. */
    public function findByCode(Workspace $workspace, string $code, bool $lock = false): ?EcommerceDiscount
    {
        return EcommerceDiscount::query()
            ->where('workspace_id', $workspace->id)
            ->where('code', $code)
            ->when($lock, fn ($query) => $query->lockForUpdate())
            ->with(self::WEIGHED)
            ->first();
    }

    /**
     * Phase H2a: $workspace's automatic discounts that are on, on $today (the
     * business's) - locked when $lock, inside a transaction - with what and
     * (Phase H2b) whom they are for.
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
            ->with(self::WEIGHED)
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

    /**
     * Made through the business, so its workspace is never read from $data;
     * with what and whom it is for - $links: its products, categories and
     * contacts, by id.
     *
     * @param  array{products: list<int>, categories: list<int>, contacts: list<int>}  $links
     */
    public function create(Workspace $workspace, array $data, array $links): EcommerceDiscount
    {
        $discount = new EcommerceDiscount($data);
        $discount->workspace_id = $workspace->id;
        $discount->save();
        $this->link($discount, $links);

        return $discount;
    }

    /** $links, when given, are what and whom it is for from now on. */
    public function update(EcommerceDiscount $discount, array $data, ?array $links = null): void
    {
        $discount->update($data);

        if ($links !== null) {
            $this->link($discount, $links);
        }
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

    /** Phase H2b: of $ids, those that are $workspace's item categories. */
    public function categoryIdsOf(Workspace $workspace, array $ids): array
    {
        return ItemCategory::query()->where('workspace_id', $workspace->id)->whereIn('id', $ids)->pluck('id')->all();
    }

    /** Phase H2b: $workspace's item categories, by name - those matching $search - for a discount to be for: up to 20, each id and name. */
    public function categoryChoices(Workspace $workspace, ?string $search): Collection
    {
        return ItemCategory::query()
            ->where('workspace_id', $workspace->id)
            ->when($search, fn ($query, $search) => $query->where('name', 'like', "%{$search}%"))
            ->orderBy('name')
            ->limit(20)
            ->get(['id', 'name']);
    }

    /** Phase H2b: of $ids, those that are $workspace's customers - contacts that buy, not merged into another. */
    public function contactIdsOf(Workspace $workspace, array $ids): array
    {
        return $this->customers($workspace)->whereIn('id', $ids)->pluck('id')->all();
    }

    /**
     * Phase H2b: $workspace's active customers, by name - those whose name or
     * e-mail matches $search - for a discount to be for: up to 20, each id,
     * name and e-mail.
     */
    public function contactChoices(Workspace $workspace, ?string $search): Collection
    {
        return $this->customers($workspace)
            ->where('is_active', true)
            ->when($search, fn ($query, $search) => $query->where(fn ($query) => $query->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%")))
            ->orderBy('name')
            ->limit(20)
            ->get(['id', 'name', 'email']);
    }

    /** What and whom $discount is for, as $links say. */
    private function link(EcommerceDiscount $discount, array $links): void
    {
        $discount->products()->sync($links['products']);
        $discount->categories()->sync($links['categories']);
        $discount->contacts()->sync($links['contacts']);
    }

    /** $workspace's customers: contacts that buy, not merged into another. */
    private function customers(Workspace $workspace): Builder
    {
        return Contact::query()
            ->where('workspace_id', $workspace->id)
            ->whereNull('merged_into_id')
            ->whereIn('role', [Contact::ROLE_CUSTOMER, Contact::ROLE_BOTH]);
    }

    /** Only the uses whose order is not cancelled. */
    private function notCancelled(Builder $query): Builder
    {
        return $query->whereHas('order', fn (Builder $query) => $query->where('status', '!=', EcommerceOrder::CANCELLED));
    }
}
