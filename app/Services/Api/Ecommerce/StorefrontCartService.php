<?php

namespace App\Services\Api\Ecommerce;

use App\Models\User\CatalogProduct;
use App\Models\User\CatalogProductVariant;
use App\Models\User\EcommerceCart;
use App\Models\User\Storefront;
use App\Models\User\User;
use App\Repositories\User\EcommerceCartRepository;
use App\Repositories\User\EcommerceSettingRepository;
use App\Repositories\User\StorefrontCatalogRepository;
use App\Services\Shared\ActivityLogService;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * A storefront's cart (Doc 19 section 19.14 - Phase D3), for its visitor -
 * no sign-in - by a token their browser keeps:
 *
 * - made with its first product, in the store's currency, the token given
 *   back once; kept while it is used, and expired after Settings →
 *   eCommerce's days without use (expire(), hourly);
 * - a product added - a listing the store shows, sold at its price, its
 *   variant chosen when it has them, priced - to its line, or a new one, up
 *   to 50 lines and 999 of a product, and never beyond what a Limited one
 *   has left or for one out of stock;
 * - a line's quantity changed, or the line removed.
 *
 * Every answer reads the cart again as the store sells it now
 * (StorefrontCartLines), and keeps its prices and totals as shown. A cart is
 * not an order or a sale, and reserves nothing.
 *
 * From Phase H3a (19.32) an abandoned cart is its visitor's still, and Active
 * again once used - opened, changed or at checkout, where it learns its
 * customer (atCheckout()). One whose customer is known, unused for Settings →
 * eCommerce's hours, is abandoned (abandon(), hourly).
 */
class StorefrontCartService
{
    public function __construct(
        protected StorefrontCatalogService $stores,
        protected StorefrontCatalogRepository $catalog,
        protected EcommerceCartRepository $carts,
        protected EcommerceSettingRepository $settings,
        protected StorefrontPricing $pricing,
        protected StorefrontCartLines $cartLines,
    ) {}

    /**
     * The visitor's cart at the store at $code - none while $token names no
     * active cart there.
     *
     * @return array{cart: ?EcommerceCart, read: ?array, terms: array, token: ?string}
     */
    public function show(string $code, ?string $token, ?User $previewer = null): array
    {
        ['storefront' => $storefront, 'today' => $today] = $this->stores->openStore($code, $previewer);
        $cart = $token ? $this->carts->findActive($storefront, $this->hash($token)) : null;

        return $this->answer($storefront, $cart, $today, null);
    }

    /** $data: product (its link), variant_id, quantity. A new cart's token is given back once. */
    public function add(string $code, ?string $token, array $data, ?User $previewer = null): array
    {
        ['storefront' => $storefront, 'today' => $today] = $this->stores->openStore($code, $previewer);
        $terms   = $this->pricing->terms($storefront->workspace, $storefront, $today);
        $product = $this->catalog->product($storefront, $data['product'], $today)
            ?? throw ValidationException::withMessages(['product' => 'This product is not sold in this store.']);
        $variant = $this->variantOf($product, $data['variant_id'] ?? null);
        $item    = $variant?->item ?? $product->item;

        if ($this->pricing->unitPrice($item, $terms, '1')['price'] === null) {
            throw ValidationException::withMessages(['product' => 'This product has no price yet: ask the store about it.']);
        }

        $cart     = $token ? $this->carts->findActive($storefront, $this->hash($token)) : null;
        $line     = $cart ? $this->carts->lineOf($cart, $product->id, $variant?->id) : null;
        $quantity = bcadd($line ? (string) $line->quantity : '0', (string) $data['quantity'], 3);

        if (! $line && $cart && $cart->lines->count() >= EcommerceCart::MAX_LINES) {
            throw ValidationException::withMessages(['product' => 'Your cart holds up to '.EcommerceCart::MAX_LINES.' products: check out, or remove some first.']);
        }

        $this->checkStock($variant ?? $product, $quantity);

        // Only once all is well is a cart made: a refused first product leaves none behind.
        $created = null;

        if (! $cart) {
            $created = Str::random(48);
            $cart    = $this->carts->create($storefront, ['token_hash' => $this->hash($created), 'currency' => $terms['currency'], ...$this->kept($storefront)]);
        }

        $line
            ? $this->carts->updateLine($line, ['quantity' => $quantity])
            : $this->carts->addLine($cart, ['catalog_product_id' => $product->id, 'catalog_product_variant_id' => $variant?->id, 'item_id' => $item->id, 'quantity' => $quantity]);

        return $this->answer($storefront, $cart, $today, $created);
    }

    /** $lineId's quantity set to $quantity - checked against what is left, for a product the store still sells. */
    public function update(string $code, ?string $token, int $lineId, string $quantity, ?User $previewer = null): array
    {
        ['storefront' => $storefront, 'today' => $today, 'cart' => $cart, 'line' => $line] = $this->lineAt($code, $token, $lineId, $previewer);
        $product = $this->catalog->shownAmong($storefront, [$line->catalog_product_id], $today)->first();
        $variant = $product?->variants->firstWhere('id', $line->catalog_product_variant_id);

        if (! $product || $product->sales_method !== CatalogProduct::FIXED_PRICE || ($line->catalog_product_variant_id !== null && ! $variant)) {
            throw ValidationException::withMessages(['quantity' => 'This product is no longer sold here: remove it from your cart.']);
        }

        $this->checkStock($variant ?? $product, $quantity);
        $this->carts->updateLine($line, ['quantity' => $quantity]);

        return $this->answer($storefront, $cart, $today, null);
    }

    public function remove(string $code, ?string $token, int $lineId, ?User $previewer = null): array
    {
        ['storefront' => $storefront, 'today' => $today, 'cart' => $cart, 'line' => $line] = $this->lineAt($code, $token, $lineId, $previewer);

        $this->carts->deleteLine($cart, $line);

        return $this->answer($storefront, $cart, $today, null);
    }

    /** The carts whose days are over, expired - (Phase H3a) their customer's details cleared: how many (the scheduler's, hourly). */
    public function expire(): int
    {
        return $this->carts->expire(now());
    }

    /**
     * Phase H3a: the active carts whose customer is known, unused for their
     * business's hours, abandoned: how many (the scheduler's, hourly). No
     * business's hours are fewer than one, so only those with a cart unused
     * for an hour are looked at.
     */
    public function abandon(): int
    {
        $now        = now();
        $businesses = $this->carts->idleBusinesses($now->copy()->subHour());
        $hours      = $this->settings->abandonedHoursOf($businesses);

        return array_sum(array_map(fn (int $id) => $this->carts->abandon($id, $now->copy()->subHours($hours[$id]), $now), $businesses));
    }

    /**
     * Phase H3a: $cart at checkout - used now, so active again and its days
     * counted from now - with its customer as checkout knows them:
     * $customer holds the cart's fields learnt (StorefrontOrderPlacement).
     */
    public function atCheckout(Storefront $storefront, EcommerceCart $cart, array $customer): void
    {
        $this->carts->update($cart, ['status' => EcommerceCart::ACTIVE, ...$this->kept($storefront), ...$customer]);
    }

    /**
     * The cart read as the store sells it now - its days counted again from
     * now, (Phase H3a) active again if it was abandoned, and its prices and
     * totals kept as shown. A cart's changes are no business activity: none
     * is logged, even for its team previewing.
     */
    private function answer(Storefront $storefront, ?EcommerceCart $cart, string $today, ?string $created): array
    {
        ActivityLogService::skipForThisRequest();

        $terms = $this->pricing->terms($storefront->workspace, $storefront, $today);

        if (! $cart) {
            return ['cart' => null, 'read' => null, 'terms' => $terms, 'token' => null];
        }

        $read = $this->cartLines->read($storefront, $cart, $terms, $today);

        $this->carts->savePrices($cart, collect($read['lines'])->mapWithKeys(fn (array $line) => [$line['line']->id => $line['unit']])->all());
        $this->carts->update($cart, [
            'status'      => EcommerceCart::ACTIVE,
            'currency'    => $terms['currency'],
            'subtotal'    => $read['shown']['subtotal'],
            'tax_total'   => $read['shown']['tax_total'],
            'grand_total' => $read['shown']['grand_total'],
            ...$this->kept($storefront),
        ]);

        return ['cart' => $cart, 'read' => $read, 'terms' => $terms, 'token' => $created];
    }

    /** The line $lineId of the visitor's cart, with the cart and its store. */
    private function lineAt(string $code, ?string $token, int $lineId, ?User $previewer): array
    {
        ['storefront' => $storefront, 'today' => $today] = $this->stores->openStore($code, $previewer);
        $cart = ($token ? $this->carts->findActive($storefront, $this->hash($token)) : null) ?? abort(404, 'Your cart has expired. Add the products again.');
        $line = $cart->lines->firstWhere('id', $lineId) ?? abort(404, 'This product is no longer in your cart.');

        return ['storefront' => $storefront, 'today' => $today, 'cart' => $cart, 'line' => $line];
    }

    /** The variant of $product with $id: one it shows, when it has them - none when it has none. */
    private function variantOf(CatalogProduct $product, mixed $id): ?CatalogProductVariant
    {
        if ($product->sales_method !== CatalogProduct::FIXED_PRICE) {
            throw ValidationException::withMessages(['product' => 'This product is not sold online at a price: ask the store about it.']);
        }

        if ($product->variants->isEmpty()) {
            return $id === null ? null : throw ValidationException::withMessages(['variant_id' => 'This product has no options to choose.']);
        }

        return $product->variants->firstWhere('id', (int) $id) ?? throw ValidationException::withMessages(['variant_id' => 'Choose its options.']);
    }

    /** $quantity of $stock - a listing, or a variant of one - in stock: never one out of stock, nor beyond what a Limited one has left, nor beyond 999. */
    private function checkStock(CatalogProduct|CatalogProductVariant $stock, string $quantity): void
    {
        if (bccomp($quantity, (string) EcommerceCart::MAX_QUANTITY, 3) > 0) {
            throw ValidationException::withMessages(['quantity' => 'Up to '.EcommerceCart::MAX_QUANTITY.' of a product.']);
        }

        if ($stock->availability === 'out_of_stock') {
            throw ValidationException::withMessages(['quantity' => 'Out of stock.']);
        }

        $left = (string) ($stock->stock_quantity ?? '0');

        if ($stock->availability === CatalogProduct::LIMITED && bccomp($quantity, $left, 3) > 0) {
            throw ValidationException::withMessages(['quantity' => bccomp($left, '0', 3) > 0 ? 'Only '.rtrim(rtrim($left, '0'), '.').' left.' : 'Out of stock.']);
        }
    }

    /** Used now: its last use, and when its days are over. */
    private function kept(Storefront $storefront): array
    {
        return ['last_activity_at' => now(), 'expires_at' => now()->addDays($this->settings->forWorkspace($storefront->workspace)->cart_days ?: 30)];
    }

    private function hash(string $token): string
    {
        return hash('sha256', $token);
    }
}
