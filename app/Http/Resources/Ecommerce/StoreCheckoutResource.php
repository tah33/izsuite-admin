<?php

namespace App\Http\Resources\Ecommerce;

use App\Models\User\EcommercePaymentMethod;
use App\Models\User\PickupPoint;
use App\Support\AddressFormat;
use App\Support\Countries;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A cart at checkout (Doc 19 Phase D4), as StorefrontCheckout works it out:
 * its lines (StoreCartResource's), whether it has anything to deliver and the
 * countries the store delivers to; the shipping methods offered for the
 * address - each at its price as the store shows prices, none when quoted
 * after the order - the pickup points, and the ways to pay offered; what is
 * chosen among them; the totals; what checkout asks (guests, phone, note);
 * and the store's terms and privacy policy, when published.
 */
class StoreCheckoutResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        ['quote' => $quote, 'storefront' => $storefront, 'settings' => $settings, 'policies' => $policies] = $this->resource;
        $terms = $quote['terms'];

        return [
            'cart'             => (new StoreCartResource(['read' => $quote['read'], 'terms' => $terms, 'token' => null]))->resolve($request),
            'needs_delivery'   => $quote['needs_delivery'],
            'countries'        => array_values(array_map(fn (string $code) => ['code' => $code, 'name' => Countries::name($code) ?? $code], $storefront->countries ?? [])),
            'shipping_methods' => array_map(fn (array $offer) => [
                'id'       => $offer['method']->id,
                'name'     => $offer['method']->name,
                'kind'     => $offer['method']->kind,
                'price'    => $offer['shown'],
                'days_min' => $offer['method']->days_min,
                'days_max' => $offer['method']->days_max,
            ], $quote['methods']),
            'pickup_points'    => $quote['points']->map(fn (PickupPoint $point) => [
                'id'           => $point->id,
                'name'         => $point->location->name,
                'address'      => implode(', ', AddressFormat::lines(Countries::addressFormat($point->location->country), [
                    'line1' => $point->location->address_line_1, 'line2' => $point->location->address_line_2, 'city' => $point->location->city,
                    'region' => $point->location->state, 'postal' => $point->location->postal_code, 'country' => Countries::name($point->location->country),
                ])) ?: null,
                'instructions' => $point->instructions,
                'ready_within' => $point->ready_within,
            ])->values(),
            'payment_methods'  => $quote['payments']->map(fn (EcommercePaymentMethod $method) => [
                'id'           => $method->id,
                'name'         => $method->name,
                'type'         => $method->type,
                'instructions' => $method->instructions,
                'reference'    => $method->reference,
            ])->values(),
            'chosen'           => [
                'delivery'           => $quote['delivery'],
                'shipping_method_id' => $quote['method']['method']->id ?? null,
                'pickup_point_id'    => $quote['point']?->id,
                'payment_method_id'  => $quote['payment']?->id,
            ],
            'totals'           => [...$quote['shown'], 'currency' => $terms['currency'], 'prices_include_tax' => $terms['with_tax'], 'quoted_shipping' => $quote['method'] !== null && $quote['method']['price'] === null],
            'checkout'         => ['guests' => (bool) $settings->guest_checkout, 'phone' => $settings->checkout_phone, 'notes' => (bool) $settings->checkout_notes, 'open' => $this->resource['open']],
            'policies'         => $policies,
        ];
    }
}
