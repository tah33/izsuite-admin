<?php

namespace App\Repositories\User;

use App\Models\User\CatalogProduct;
use App\Models\User\EcommerceDiscount;
use App\Models\User\EcommerceOrder;
use App\Models\User\Workspace;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * A business's discounts (Doc 19 section 19.31 - Phase H1): a page of them
 * with their uses, one by id or code - locked, as an order is placed - made,
 * changed and deleted with the products they are for; their uses, in all
 * and by one e-mail; and the listings a discount may be for.
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
            ->withCount(['orders as uses' => fn ($query) => $query->where('status', '!=', EcommerceOrder::CANCELLED)])
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    /** $workspace's discount $id, with its products and uses - or none. */
    public function findFor(Workspace $workspace, int $id): ?EcommerceDiscount
    {
        return EcommerceDiscount::query()
            ->where('workspace_id', $workspace->id)
            ->with('products:id,title')
            ->withCount(['orders as uses' => fn ($query) => $query->where('status', '!=', EcommerceOrder::CANCELLED)])
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

    /** Whether another of $workspace's discounts has $code. */
    public function codeTaken(Workspace $workspace, string $code, ?int $except = null): bool
    {
        return EcommerceDiscount::query()->where('workspace_id', $workspace->id)->where('code', $code)->when($except, fn ($query) => $query->whereKeyNot($except))->exists();
    }

    /** $discount's uses: its orders that are not cancelled - of $email alone, when given. */
    public function usesOf(EcommerceDiscount $discount, ?string $email = null): int
    {
        return EcommerceOrder::query()
            ->where('ecommerce_discount_id', $discount->id)
            ->where('status', '!=', EcommerceOrder::CANCELLED)
            ->when($email, fn ($query, $email) => $query->where('customer_email', mb_strtolower($email)))
            ->count();
    }

    /** Whether $discount was ever used on an order, cancelled or not. */
    public function everUsed(EcommerceDiscount $discount): bool
    {
        return EcommerceOrder::query()->where('ecommerce_discount_id', $discount->id)->exists();
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
}
