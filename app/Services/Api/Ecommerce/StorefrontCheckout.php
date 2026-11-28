<?php

namespace App\Services\Api\Ecommerce;

use App\Models\User\EcommerceCart;
use App\Models\User\EcommerceOrder;
use App\Models\User\Item;
use App\Models\User\PickupPoint;
use App\Models\User\ShippingMethod;
use App\Models\User\Storefront;
use App\Models\User\Workspace;
use App\Repositories\User\ShippingRepository;
use App\Services\Api\Sales\SalesLines;
use App\Services\Sales\SalesCalculator;
use App\Support\Decimal;
use Illuminate\Support\Arr;

/**
 * What a cart comes to at checkout (Doc 19 sections 19.15, 19.20, 19.22 and
 * 19.26 - Phase D4), worked out again each time from the store as it sells
 * now:
 *
 * - its products (StorefrontCartLines) - those to deliver being physical
 *   products and combos, not digital products or services;
 * - with something to deliver: the shipping methods of the zone its address
 *   is in (ShippingRates) - only in a country the storefront sells to - each
 *   at its price in the store's currency as the store shows prices, and the
 *   pickup points; with nothing to deliver, neither;
 * - the delivery chosen: a method offered - its price a line of its own with
 *   its tax, none while quoted after the order - or a pickup point offered;
 * - the payment methods for its currency, country, total and delivery
 *   (EcommercePaymentOptions), and the one chosen among them;
 * - its totals, as its invoice will be: its products and its shipping - less,
 *   from Phase H1, what a discount code takes off, and from Phase H2a the
 *   automatic discounts that go with it (StorefrontDiscounts).
 *
 * A choice no longer offered is simply not chosen; placing the order
 * (StorefrontOrderPlacement) refuses it.
 */
class StorefrontCheckout
{
    private const TOTALS = ['subtotal', 'discount_total', 'tax_total', 'grand_total', 'net_total', 'base_grand_total', 'base_tax_total', 'base_net_total', 'taxes'];

    public function __construct(
        protected StorefrontPricing $pricing,
        protected StorefrontCartLines $cartLines,
        protected ShippingRates $shippingRates,
        protected ShippingRepository $shipping,
        protected EcommercePaymentOptions $paymentOptions,
        protected SalesLines $salesLines,
        protected SalesCalculator $calculator,
        protected StorefrontDiscounts $discounts,
    ) {}

    /**
     * $choices: the address it is delivered to - country, region, city,
     * postal_code - the billing country, the delivery (shipping or pickup),
     * shipping_method_id, pickup_point_id and payment_method_id; for the
     * discounts (Phases H1 to H2b) discount_code, email, account - the
     * StorefrontCustomer signed in, if any - and lock_discount.
     */
    public function quote(Storefront $storefront, EcommerceCart $cart, array $choices, string $today): array
    {
        $workspace = $storefront->workspace;
        $terms     = $this->pricing->terms($workspace, $storefront, $today);
        $read      = $this->cartLines->read($storefront, $cart, $terms, $today);
        $shipped   = array_values(array_filter($read['lines'], fn (array $line) => $line['problem'] === null && $this->ships($line['item'])));
        $address   = Arr::only($choices['address'] ?? [], ['country', 'region', 'city', 'postal_code']);
        $country   = strtoupper(trim((string) ($address['country'] ?? '')));
        $sellsTo   = $country !== '' && in_array($country, $storefront->countries ?? [], true);
        $parcel    = $this->parcel($shipped, $read['totals']['grand_total'] ?? '0.00', $terms);

        $methods = $shipped !== [] && $sellsTo ? $this->offers($workspace, $address, $parcel, $terms) : [];
        $points  = $shipped !== [] ? $this->shipping->offeredPickupPoints($workspace) : collect();

        $delivery = $shipped === [] ? EcommerceOrder::NONE : (in_array($choices['delivery'] ?? null, [EcommerceOrder::SHIPPING, EcommerceOrder::PICKUP], true) ? $choices['delivery'] : null);
        $method   = $delivery === EcommerceOrder::SHIPPING ? collect($methods)->first(fn (array $offer) => $offer['method']->id === (int) ($choices['shipping_method_id'] ?? 0)) : null;
        $point    = $delivery === EcommerceOrder::PICKUP ? $points->first(fn (PickupPoint $point) => $point->id === (int) ($choices['pickup_point_id'] ?? 0)) : null;

        $rows = $read['rows'];

        if ($method && $method['price'] !== null) {
            $rows[] = $this->shippingRow($workspace, $method, $terms);
        }

        // Phases H1 and H2a: the discounts it takes - the code given and the automatic ones - checked again
        // each time; what they take off shared over the order, as an invoice's discount.
        // Phase H2b: and the account its customer is signed in to, for the discounts of those signed in or of chosen contacts.
        $discounts = $this->discounts->take($workspace, $choices['discount_code'] ?? null, $rows, $read, $terms, $parcel, $today, $choices['email'] ?? null, $choices['account'] ?? null, (bool) ($choices['lock_discount'] ?? false));
        $off       = $discounts['off'];

        $totals  = $this->totals($workspace, $rows, $terms, $off);
        $billing = strtoupper(trim((string) ($choices['billing_country'] ?? '')));
        $paying  = $this->paymentOptions->for($workspace, $terms['currency'], $delivery === EcommerceOrder::SHIPPING ? $country : ($billing ?: $country), $totals['totals']['grand_total'], match ($delivery) {
            EcommerceOrder::SHIPPING => 'shipped',
            EcommerceOrder::PICKUP   => 'pickup',
            default                  => EcommercePaymentOptions::NOTHING,
        });

        return [
            'terms'          => $terms,
            'read'           => $read,
            'needs_delivery' => $shipped !== [],
            'country'        => $country,
            'sells_to'       => $sellsTo,
            'parcel'         => $parcel,
            'methods'        => $methods,
            'points'         => $points,
            'delivery'       => $delivery,
            'method'         => $method,
            'point'          => $point,
            'payments'       => $paying,
            'payment'        => $paying->firstWhere('id', (int) ($choices['payment_method_id'] ?? 0)),
            'rows'           => $totals['rows'],
            'totals'         => $totals['totals'],
            'shown'          => $totals['shown'],
            // Phase H1: the code given - or why it does not apply. Phase H2a: the discounts taken, in the
            // order taken, each with its share of the discount as the store shows it; and what they take off.
            'discount'       => $discounts['code'],
            'discounts'      => $this->shown($discounts['taken'], $totals['shown']['discount']),
            'off'            => $off,
        ];
    }

    /**
     * Phase H2a: $taken, each with its share of $discount - what they take
     * off as the store shows prices - by what it takes off.
     *
     * @return list<array{discount: \App\Models\User\EcommerceDiscount, off: string, shown: string}>
     */
    private function shown(array $taken, string $discount): array
    {
        $shares = Decimal::shares($discount, array_column($taken, 'off'));

        return array_map(fn (array $one, string $share) => [...$one, 'shown' => $share], $taken, $shares);
    }

    /** Whether $item is delivered: a physical product or a combo - not a digital product or a service. */
    public function ships(?Item $item): bool
    {
        return $item !== null && ! in_array($item->type, ['digital', 'service'], true);
    }

    /** A cart line's weight in kg: its variant's each, else its listing's, by its quantity - none when neither gives one. */
    public function weightOf(array $line): ?string
    {
        $each = $line['variant']?->weight ?? $line['product']?->weight;

        return $each === null ? null : bcmul((string) $line['line']->quantity, ShippingRates::kilograms((string) $each, $line['product']->weight_unit ?? 'kg'), 3);
    }

    /**
     * What ShippingRates weighs an order by: what its products come to - in
     * the base currency - how many of them are delivered, their weight in kg
     * and their categories.
     */
    private function parcel(array $shipped, string $total, array $terms): array
    {
        return [
            'subtotal'   => bcmul($total, $terms['rate'], 2),
            'quantity'   => array_reduce($shipped, fn (string $sum, array $line) => bcadd($sum, (string) $line['line']->quantity, 3), '0'),
            'weight'     => array_reduce($shipped, fn (string $sum, array $line) => bcadd($sum, $this->weightOf($line) ?? '0', 3), '0'),
            'categories' => array_map(fn (array $line) => $line['item']->category_id, $shipped),
        ];
    }

    /** The methods of the zone $address is in that take $parcel, each with its price as the store shows it - none when quoted after the order. */
    public function offers(Workspace $workspace, array $address, array $parcel, array $terms): array
    {
        return array_map(fn (array $offer) => [
            ...$offer,
            'shown' => $offer['price'] === null ? null : $this->pricing->shownBase($offer['price'], $this->usableTax($offer['method'], $terms), $terms),
        ], $this->shippingRates->for($workspace, $address, $parcel)['methods']);
    }

    /** $method's tax, while the business may use it now - else none. */
    private function usableTax(ShippingMethod $method, array $terms): ?int
    {
        return $method->tax_rate_id !== null && isset($terms['rates'][$method->tax_rate_id]) ? $method->tax_rate_id : null;
    }

    /** The shipping as an invoice line: "Shipping: " and its method's name, one of it at its price in the store's currency, with its tax. */
    public function shippingRow(Workspace $workspace, array $offer, array $terms): array
    {
        return [
            ...$this->salesLines->rows($workspace, [[
                'name'        => 'Shipping: '.$offer['method']->name,
                'quantity'    => '1',
                'unit_price'  => $this->pricing->inCurrency($offer['price'], $terms),
                'tax_rate_id' => $this->usableTax($offer['method'], $terms),
            ]])[0],
            'kind' => 'shipping',
        ];
    }

    /**
     * $rows totalled as the invoice will be - less $off, a discount's amount
     * shared over them as Sales shares an invoice's (Phase H1) - each with its
     * amounts and what it shows; and the totals as the store shows them: its
     * products, its shipping, the discount, the tax and the total. Each row
     * shows as it is before the discount, so a discount never changes what a
     * line shows (as an order's, Phase F5b).
     */
    private function totals(Workspace $workspace, array $rows, array $terms, ?string $off = null): array
    {
        $lines    = array_map(fn (array $row) => $this->salesLines->forCalculation($row), $rows);
        $plain    = $this->calculator->calculate($lines, null, null, $workspace->price_tax_mode, $workspace->tax_rounding, $terms['rate']);
        $result   = $off === null ? $plain : $this->calculator->calculate($lines, EcommerceOrder::AMOUNT, $off, $workspace->price_tax_mode, $workspace->tax_rounding, $terms['rate']);
        $products = '0.00';
        $shipping = '0.00';

        foreach ($rows as $key => $row) {
            $shown      = $this->pricing->shownAmount($plain['lines'][$key], $terms);
            $rows[$key] = [...$row, ...$result['lines'][$key], 'shown' => $shown];

            if (($row['kind'] ?? 'product') === 'shipping') {
                $shipping = bcadd($shipping, $shown, 2);
            } else {
                $products = bcadd($products, $shown, 2);
            }
        }

        return [
            'rows'   => $rows,
            'totals' => Arr::only($result, self::TOTALS),
            'shown'  => [
                'subtotal'    => $products,
                'shipping'    => $shipping,
                'discount'    => $off === null ? '0.00' : $this->pricing->shownDiscount($plain, $result, $terms),
                'tax_total'   => $result['tax_total'],
                'grand_total' => $result['grand_total'],
            ],
        ];
    }
}
