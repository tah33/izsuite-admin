<?php

namespace App\Services\Api\Ecommerce;

use App\Models\User\EcommerceDiscount;
use App\Models\User\Workspace;
use App\Repositories\User\EcommerceDiscountRepository;
use App\Services\Documents\DocumentFormat;
use App\Support\Decimal;

/**
 * The discounts an order takes at checkout (Doc 19 section 19.31 - Phases H1
 * and H2a), worked out by the backend at each quote and again as the order is
 * placed: the code the customer gives, and the business's automatic
 * discounts - each checked by its rules:
 *
 * - its business's, active, within its days (where the business is);
 * - the order's products meeting its minimum (in the base currency, as the
 *   shipping rates weigh them);
 * - uses left in all and by the customer's e-mail;
 * - products in the cart it is for.
 *
 * A code that fails says why; an automatic discount that fails is simply not
 * taken. Each takes off, as the business keeps prices in the store's
 * currency, a percentage of the products it is for, its amount (no more than
 * they come to) or the shipping's price.
 *
 * They are taken in order of priority - at the same priority, the one taking
 * off more first. The first is taken; each after it only when it and every
 * one taken so far go with others, so a code left out says so. One that takes
 * nothing off keeps none out. Each takes off its own share of the prices as
 * they are; together never more than the products come to (an order's
 * discount, Phase F5b), nor the shipping twice.
 */
class StorefrontDiscounts
{
    public function __construct(
        protected EcommerceDiscountRepository $discounts,
        protected StorefrontPricing $pricing,
    ) {}

    /**
     * What $code and the automatic discounts take off the cart: $rows its
     * products then its shipping, $read StorefrontCartLines' (the products'
     * listings), $parcel what the shipping rates weigh. Locked when $lock - as
     * the order is placed, inside its transaction - so their uses are counted
     * once.
     *
     * @return array{
     *     code: array{discount: ?EcommerceDiscount, code: string, problem: ?string}|null,
     *     taken: list<array{discount: EcommerceDiscount, off: string}>,
     *     off: ?string
     * }
     */
    public function take(Workspace $workspace, ?string $code, array $rows, array $read, array $terms, array $parcel, string $today, ?string $email, bool $lock = false): array
    {
        $code      = mb_strtoupper(trim((string) $code));
        $given     = $code === '' ? null : $this->discounts->findByCode($workspace, $code, $lock);
        $automatic = $this->discounts->automaticFor($workspace, $today, $lock);
        $uses      = $this->discounts->usesOf(array_values(array_filter([...$automatic->pluck('id')->all(), $given?->id])), $email, $lock);
        $cart      = $this->cart($rows, $read);
        $format    = new DocumentFormat($workspace->date_format ?? 'd M Y', $workspace->number_format ?? 'comma_dot');
        $check     = fn (EcommerceDiscount $discount) => $this->fit($discount, $cart, $terms, $parcel, $today, $email, $uses[$discount->id] ?? ['all' => 0, 'customer' => 0], $format);
        $problem   = null;
        $fits      = [];

        if ($code !== '') {
            $fit     = $given ? $check($given) : ['problem' => 'This discount code is not valid.', 'off' => null];
            $problem = $fit['problem'];

            if ($problem === null && bccomp($fit['off'], '0', 2) > 0) {
                $fits[] = ['discount' => $given, 'off' => $fit['off']];
            }
        }

        foreach ($automatic as $discount) {
            $fit = $check($discount);

            if ($fit['problem'] === null && bccomp($fit['off'], '0', 2) > 0) {
                $fits[] = ['discount' => $discount, 'off' => $fit['off']];
            }
        }

        ['taken' => $taken, 'blocker' => $blocker] = $this->together($fits, $given);

        if ($blocker !== null) {
            $problem = "This code does not go with {$blocker->name}, already on your order.";
        }

        $taken = $this->capped($taken, $cart);

        return [
            'code'  => $code === '' ? null : ['discount' => $given, 'code' => $code, 'problem' => $problem],
            'taken' => $taken,
            'off'   => $taken === [] ? null : Decimal::sum(array_column($taken, 'off')),
        ];
    }

    /**
     * Whether $discount fits the cart - else why not, as a code says it - and
     * what it takes off by itself: never more than the products come to.
     *
     * @param  array{all: int, customer: int}  $uses
     * @return array{problem: ?string, off: ?string}
     */
    private function fit(EcommerceDiscount $discount, array $cart, array $terms, array $parcel, string $today, ?string $email, array $uses, DocumentFormat $format): array
    {
        $refuse = fn (string $problem) => ['problem' => $problem, 'off' => null];

        if (! $discount->is_active) {
            return $refuse('This discount code is not valid.');
        }

        if ($discount->starts_on !== null && $discount->starts_on->toDateString() > $today) {
            return $refuse('This code works from '.$format->date($discount->starts_on->toDateString()).'.');
        }

        if ($discount->ends_on !== null && $discount->ends_on->toDateString() < $today) {
            return $refuse('This code ended on '.$format->date($discount->ends_on->toDateString()).'.');
        }

        if ($discount->minimum !== null && bccomp($parcel['subtotal'], (string) $discount->minimum, 2) < 0) {
            return $refuse('This code is for orders of '.$format->money($this->pricing->inCurrency((string) $discount->minimum, $terms), $terms['currency']).' or more.');
        }

        if ($discount->usage_limit !== null && $uses['all'] >= $discount->usage_limit) {
            return $refuse('This code has been used as often as it may be.');
        }

        if ($email && $discount->per_customer_limit !== null && $uses['customer'] >= $discount->per_customer_limit) {
            return $refuse('You have used this code as often as it may be.');
        }

        $chosen   = $discount->products->pluck('id')->all();
        $eligible = $discount->applies_to === EcommerceDiscount::PRODUCTS
            ? array_values(array_filter($cart['products'], fn (array $product) => in_array($product['listing'], $chosen, true)))
            : $cart['products'];

        if ($eligible === []) {
            return $refuse('This code is for products not in your cart.');
        }

        $base = Decimal::sum(array_column($eligible, 'base'));
        $off  = match ($discount->kind) {
            EcommerceDiscount::PERCENTAGE => Decimal::round(bcdiv(bcmul($base, (string) $discount->value, 10), '100', 10)),
            EcommerceDiscount::AMOUNT     => Decimal::min($this->pricing->inCurrency((string) $discount->value, $terms), $base),
            default                       => $cart['shipping'],
        };

        return ['problem' => null, 'off' => Decimal::min($off, $cart['all'])];
    }

    /**
     * $fits taken in order of priority - at the same priority the one taking
     * off more first, then the older: the first, and each after it while it
     * and every one taken go with others. The code given ($given), left out,
     * with the discount it does not go with.
     *
     * @param  list<array{discount: EcommerceDiscount, off: string}>  $fits
     * @return array{taken: list<array{discount: EcommerceDiscount, off: string}>, blocker: ?EcommerceDiscount}
     */
    private function together(array $fits, ?EcommerceDiscount $given): array
    {
        usort($fits, fn (array $a, array $b) => ($a['discount']->priority <=> $b['discount']->priority)
            ?: bccomp($b['off'], $a['off'], 2)
            ?: ($a['discount']->id <=> $b['discount']->id));

        $taken   = [];
        $blocker = null;

        foreach ($fits as $fit) {
            $goes = $taken === [] || ($fit['discount']->combines && array_reduce($taken, fn (bool $all, array $one) => $all && $one['discount']->combines, true));

            if ($goes) {
                $taken[] = $fit;
            } elseif ($given !== null && $fit['discount']->is($given)) {
                $blocker = (collect($taken)->first(fn (array $one) => ! $one['discount']->combines) ?? $taken[0])['discount'];
            }
        }

        return ['taken' => $taken, 'blocker' => $blocker];
    }

    /**
     * $taken, each taking off no more than is left of the products - and,
     * for free shipping, of the shipping - in the order taken; those left
     * with nothing to take off dropped.
     *
     * @param  list<array{discount: EcommerceDiscount, off: string}>  $taken
     * @return list<array{discount: EcommerceDiscount, off: string}>
     */
    private function capped(array $taken, array $cart): array
    {
        $products = $cart['all'];
        $shipping = $cart['shipping'];

        foreach ($taken as $at => $one) {
            $off = $one['off'];

            if ($one['discount']->kind === EcommerceDiscount::FREE_SHIPPING) {
                $off      = Decimal::min($off, $shipping);
                $shipping = bcsub($shipping, $off, 2);
            }

            $off      = Decimal::min($off, $products);
            $products = bcsub($products, $off, 2);

            $taken[$at]['off'] = $off;
        }

        return array_values(array_filter($taken, fn (array $one) => bccomp($one['off'], '0', 2) > 0));
    }

    /**
     * The cart as discounts weigh it: each product row's listing and its
     * price by its quantity - as the business keeps prices, to the cent - what
     * the products come to, and the shipping's.
     *
     * @return array{products: list<array{listing: ?int, base: string}>, all: string, shipping: string}
     */
    private function cart(array $rows, array $read): array
    {
        $listings = array_values(array_filter($read['lines'], fn (array $line) => $line['problem'] === null));
        $products = array_values(array_filter($rows, fn (array $row) => ($row['kind'] ?? 'product') !== 'shipping'));
        $shipped  = array_filter($rows, fn (array $row) => ($row['kind'] ?? 'product') === 'shipping');
        $weighed  = array_map(fn (array $row, int $at) => ['listing' => $listings[$at]['product']->id ?? null, 'base' => $this->base($row)], $products, array_keys($products));

        return [
            'products' => $weighed,
            'all'      => Decimal::sum(array_column($weighed, 'base')),
            'shipping' => Decimal::sum(array_map(fn (array $row) => $this->base($row), $shipped)),
        ];
    }

    /** A row's quantity by its price, to the cent, as the business keeps prices - as SalesCalculator works it out. */
    private function base(array $row): string
    {
        return Decimal::round(bcmul((string) $row['quantity'], (string) $row['unit_price'], 10));
    }
}
