<?php

namespace App\Repositories\User;

use App\Models\User\EcommerceCart;
use App\Models\User\EcommerceCartLine;
use App\Models\User\Storefront;
use App\Models\User\Workspace;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Storefront carts (Doc 19 Phase D3): a visitor's active cart by its token's
 * hash, its lines, and the carts whose days are over expired. From Phase H3a
 * (19.32): an abandoned cart is its visitor's still; the carts whose
 * customer is known left unused abandoned; an expired cart's customer
 * cleared; and the business's abandoned carts, a page at a time.
 */
class EcommerceCartRepository
{
    /** $storefront's cart known by $tokenHash, while its visitor may use it - active, or (Phase H3a) abandoned - and its days are not over; with its lines. */
    public function findActive(Storefront $storefront, string $tokenHash): ?EcommerceCart
    {
        return EcommerceCart::query()
            ->where('storefront_id', $storefront->id)
            ->where('token_hash', $tokenHash)
            ->whereIn('status', EcommerceCart::OPEN)
            ->where('expires_at', '>', now())
            ->with(['lines', 'lines.product:id,title,slug,options', 'lines.variant:id,option_values', 'lines.item:id,name'])
            ->first();
    }

    /** Phase D4: $storefront's cart known by $tokenHash, whatever its status - a cart placed already answers with its order. */
    public function findByToken(Storefront $storefront, string $tokenHash): ?EcommerceCart
    {
        return EcommerceCart::query()->where('storefront_id', $storefront->id)->where('token_hash', $tokenHash)->first();
    }

    /** Made through the storefront, so its business is never read from $data. */
    public function create(Storefront $storefront, array $data): EcommerceCart
    {
        $cart = new EcommerceCart(['storefront_id' => $storefront->id, ...$data]);
        $cart->workspace_id = $storefront->workspace_id;
        $cart->save();

        return $cart->setRelation('lines', new \Illuminate\Database\Eloquent\Collection);
    }

    /** $cart's line of $productId and $variantId, if it has one. */
    public function lineOf(EcommerceCart $cart, int $productId, ?int $variantId): ?EcommerceCartLine
    {
        return $cart->lines->first(fn (EcommerceCartLine $line) => $line->catalog_product_id === $productId && $line->catalog_product_variant_id === $variantId);
    }

    public function addLine(EcommerceCart $cart, array $data): EcommerceCartLine
    {
        $line = $cart->lines()->create($data);
        $cart->setRelation('lines', $cart->lines->push($line));

        return $line;
    }

    public function updateLine(EcommerceCartLine $line, array $data): void
    {
        $line->update($data);
    }

    public function deleteLine(EcommerceCart $cart, EcommerceCartLine $line): void
    {
        $line->delete();
        $cart->setRelation('lines', $cart->lines->reject(fn (EcommerceCartLine $kept) => $kept->id === $line->id)->values());
    }

    /** Its lines' prices as last shown, by line id. */
    public function savePrices(EcommerceCart $cart, array $prices): void
    {
        foreach ($cart->lines as $line) {
            if (array_key_exists($line->id, $prices) && $line->unit_price !== $prices[$line->id]) {
                $line->update(['unit_price' => $prices[$line->id]]);
            }
        }
    }

    public function update(EcommerceCart $cart, array $data): void
    {
        $cart->update($data);
    }

    /** The carts still open - active or abandoned - whose days are over, as of $now, marked expired, (Phase H3a) their customer's details cleared: how many. */
    public function expire(Carbon $now): int
    {
        return EcommerceCart::query()->whereIn('status', EcommerceCart::OPEN)->where('expires_at', '<=', $now)->update([
            'status' => EcommerceCart::EXPIRED, 'storefront_customer_id' => null, 'customer_name' => null, 'customer_email' => null, 'marketing' => false, 'updated_at' => $now,
        ]);
    }

    /**
     * Phase H3a: the businesses with an active cart whose customer is known,
     * unused since $since - those with carts that may be left abandoned.
     *
     * @return list<int>
     */
    public function idleBusinesses(Carbon $since): array
    {
        return $this->idle(EcommerceCart::query(), $since)->distinct()->pluck('workspace_id')->map(fn ($id) => (int) $id)->all();
    }

    /** Phase H3a: $workspaceId's active carts whose customer is known, unused since $cutoff and not yet past their days, marked abandoned as of $now: how many. */
    public function abandon(int $workspaceId, Carbon $cutoff, Carbon $now): int
    {
        return $this->idle(EcommerceCart::query()->where('workspace_id', $workspaceId), $cutoff)
            ->where('expires_at', '>', $now)
            ->update(['status' => EcommerceCart::ABANDONED, 'abandoned_at' => $now, 'updated_at' => $now]);
    }

    /**
     * Phase H3a: a page of $workspace's carts that were abandoned, at
     * $locationIds (any, when null) by their storefront's, the latest
     * activity first - narrowed by status and storefront, searched by the
     * customer's name or e-mail - each with its storefront, its lines'
     * products and, once converted, its order.
     */
    public function abandonedFor(Workspace $workspace, ?array $locationIds, array $filters, int $perPage): LengthAwarePaginator
    {
        $search = trim((string) ($filters['search'] ?? ''));

        return EcommerceCart::query()
            ->select(['id', 'storefront_id', 'storefront_customer_id', 'customer_name', 'customer_email', 'marketing', 'status', 'currency', 'grand_total', 'last_activity_at'])
            ->where('workspace_id', $workspace->id)
            ->whereNotNull('abandoned_at')
            ->when($locationIds !== null, fn (Builder $query) => $query->whereIn('storefront_id', Storefront::query()->select('id')->where('workspace_id', $workspace->id)->whereIn('location_id', $locationIds)))
            ->when($filters['storefront_id'] ?? null, fn (Builder $query, int|string $id) => $query->where('storefront_id', (int) $id))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($search !== '', fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->where('customer_name', 'like', "%{$search}%")
                ->orWhere('customer_email', 'like', '%'.mb_strtolower($search).'%')))
            ->with(['storefront:id,name', 'lines:id,ecommerce_cart_id,catalog_product_id,quantity', 'lines.product:id,title', 'order:id,ecommerce_cart_id,number'])
            ->orderByDesc('last_activity_at')
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    /** Of $query, the active carts whose customer is known, unused since $since. */
    private function idle(Builder $query, Carbon $since): Builder
    {
        return $query->where('status', EcommerceCart::ACTIVE)->whereNotNull('customer_email')->where('last_activity_at', '<=', $since);
    }
}
