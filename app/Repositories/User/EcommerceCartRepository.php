<?php

namespace App\Repositories\User;

use App\Models\User\EcommerceCart;
use App\Models\User\EcommerceCartLine;
use App\Models\User\Storefront;
use Illuminate\Support\Carbon;

/**
 * Storefront carts (Doc 19 Phase D3): a visitor's active cart by its token's
 * hash, its lines, and the carts whose days are over expired.
 */
class EcommerceCartRepository
{
    /** $storefront's cart known by $tokenHash, while it is active and its days are not over - with its lines. */
    public function findActive(Storefront $storefront, string $tokenHash): ?EcommerceCart
    {
        return EcommerceCart::query()
            ->where('storefront_id', $storefront->id)
            ->where('token_hash', $tokenHash)
            ->where('status', EcommerceCart::ACTIVE)
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

    /** The active carts whose days are over, as of $now, marked expired: how many. */
    public function expire(Carbon $now): int
    {
        return EcommerceCart::query()->where('status', EcommerceCart::ACTIVE)->where('expires_at', '<=', $now)->update(['status' => EcommerceCart::EXPIRED, 'updated_at' => $now]);
    }
}
