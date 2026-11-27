<?php

namespace App\Services\Api\Ecommerce;

use App\Models\User\EcommerceDiscount;
use App\Models\User\Workspace;
use App\Repositories\User\EcommerceDiscountRepository;
use App\Services\Documents\DocumentFormat;
use App\Support\Decimal;

/**
 * A discount code at checkout (Doc 19 section 19.31 - Phase H1), checked by
 * the backend at each quote and again as the order is placed - its
 * business's, active, within its days (where the business is), the order's
 * products meeting its minimum (in the base currency, as the shipping rates
 * weigh them), uses left in all and by the customer's e-mail, and products in
 * the cart it is for - and what it takes off, as the business keeps prices in
 * the store's currency: a percentage of the products it is for, its amount
 * (no more than they come to), or the shipping's price; never more than the
 * products come to, as an order's discount (Phase F5b).
 */
class StorefrontDiscounts
{
    public function __construct(
        protected EcommerceDiscountRepository $discounts,
        protected StorefrontPricing $pricing,
    ) {}

    /**
     * $code against the cart: $rows its products then its shipping, $read
     * StorefrontCartLines' (the products' listings), $parcel what the shipping
     * rates weigh. Locked when $lock - as the order is placed, inside its
     * transaction - so a code's uses are counted once. Null without a code.
     *
     * @return array{discount: ?EcommerceDiscount, code: string, off: ?string, problem: ?string}|null
     */
    public function check(Workspace $workspace, ?string $code, array $rows, array $read, array $terms, array $parcel, string $today, ?string $email, bool $lock = false): ?array
    {
        $code = mb_strtoupper(trim((string) $code));

        if ($code === '') {
            return null;
        }

        $discount = $this->discounts->findByCode($workspace, $code, $lock);
        $format   = new DocumentFormat($workspace->date_format ?? 'd M Y', $workspace->number_format ?? 'comma_dot');
        $refuse   = fn (string $problem) => ['discount' => $discount, 'code' => $code, 'off' => null, 'problem' => $problem];

        if (! $discount || ! $discount->is_active) {
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

        if ($discount->usage_limit !== null && $this->discounts->usesOf($discount) >= $discount->usage_limit) {
            return $refuse('This code has been used as often as it may be.');
        }

        if ($email && $discount->per_customer_limit !== null && $this->discounts->usesOf($discount, $email) >= $discount->per_customer_limit) {
            return $refuse('You have used this code as often as it may be.');
        }

        // The products in the cart - each row with its listing - and those it is for.
        $listings = array_values(array_filter($read['lines'], fn (array $line) => $line['problem'] === null));
        $products = array_values(array_filter($rows, fn (array $row) => ($row['kind'] ?? 'product') !== 'shipping'));
        $chosen   = $discount->products->pluck('id')->all();
        $eligible = $discount->applies_to === EcommerceDiscount::PRODUCTS
            ? array_values(array_filter($products, fn (array $row, int $at) => in_array($listings[$at]['product']->id ?? null, $chosen, true), ARRAY_FILTER_USE_BOTH))
            : $products;

        if ($eligible === []) {
            return $refuse('This code is for products not in your cart.');
        }

        $all      = Decimal::sum(array_map(fn (array $row) => $this->base($row), $products));
        $base     = Decimal::sum(array_map(fn (array $row) => $this->base($row), $eligible));
        $shipping = Decimal::sum(array_map(fn (array $row) => $this->base($row), array_filter($rows, fn (array $row) => ($row['kind'] ?? 'product') === 'shipping')));

        $off = match ($discount->kind) {
            EcommerceDiscount::PERCENTAGE => Decimal::round(bcdiv(bcmul($base, (string) $discount->value, 10), '100', 10)),
            EcommerceDiscount::AMOUNT     => Decimal::min($this->pricing->inCurrency((string) $discount->value, $terms), $base),
            default                       => $shipping,
        };

        return ['discount' => $discount, 'code' => $code, 'off' => Decimal::min($off, $all), 'problem' => null];
    }

    /** A row's quantity by its price, to the cent, as the business keeps prices - as SalesCalculator works it out. */
    private function base(array $row): string
    {
        return Decimal::round(bcmul((string) $row['quantity'], (string) $row['unit_price'], 10));
    }
}
