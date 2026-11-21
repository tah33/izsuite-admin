<?php

namespace App\Services\Api\Ecommerce;

use App\Models\User\ShippingMethod;
use App\Models\User\ShippingZone;
use App\Models\User\Workspace;
use App\Repositories\User\ShippingRepository;
use Illuminate\Support\Collection;

/**
 * What it costs to ship an order to an address (Doc 19 section 19.26 - Phase
 * D1), for the checkout (Phase D4):
 *
 * - the zone: the first, in the zones' order, whose countries hold the
 *   address's country and whose regions, cities and postal codes - those it
 *   names - hold its own: names whatever their case, postal codes whatever
 *   their spaces, a code ending "*" holding every code that starts so;
 * - its methods that are on and take the order: within their order values
 *   and weights, and carrying each of its categories when they name some;
 * - each at its price: a flat rate's or local delivery's own, nothing when
 *   free, the band the order's weight, value or quantity reaches - none
 *   reached, the method is not offered - and none yet when quoted after the
 *   order.
 *
 * Amounts are in the base currency, as the methods keep them; weights in kg.
 */
class ShippingRates
{
    /** Each unit a listing's weight may be in (Phase B1), in kg. */
    private const KILOGRAMS = ['kg' => '1', 'g' => '0.001', 'lb' => '0.45359237', 'oz' => '0.028349523125'];

    public function __construct(
        protected ShippingRepository $shipping,
    ) {}

    /**
     * The zone $address is in, and its methods that take $parcel, each with
     * its price - null when quoted after the order. $address: country,
     * region, city and postal_code. $parcel: subtotal (the base currency),
     * quantity, weight (kg) and categories - the category ids of what is
     * shipped, null for one with none.
     *
     * @return array{zone: ?ShippingZone, methods: list<array{method: ShippingMethod, price: ?string}>}
     */
    public function for(Workspace $workspace, array $address, array $parcel): array
    {
        $zone = $this->zoneOf($this->shipping->offeredZones($workspace), $address);

        if (! $zone) {
            return ['zone' => null, 'methods' => []];
        }

        $methods = [];

        foreach ($zone->methods as $method) {
            if ($this->takes($method, $parcel) && ($priced = $this->priced($method, $parcel)) !== false) {
                $methods[] = ['method' => $method, 'price' => $priced];
            }
        }

        return ['zone' => $zone, 'methods' => $methods];
    }

    /** $weight in $unit, in kg. */
    public static function kilograms(string $weight, string $unit): string
    {
        return bcmul($weight, self::KILOGRAMS[$unit] ?? '1', 6);
    }

    /** The first of $zones $address is in. */
    public function zoneOf(Collection $zones, array $address): ?ShippingZone
    {
        $country = strtoupper(trim((string) ($address['country'] ?? '')));

        return $zones->first(fn (ShippingZone $zone) => in_array($country, $zone->countries, true)
            && $this->named($zone->regions, $address['region'] ?? null)
            && $this->named($zone->cities, $address['city'] ?? null)
            && $this->postal($zone->postal_codes, $address['postal_code'] ?? null));
    }

    /** Whether $method takes $parcel: its order values, weights and categories. */
    private function takes(ShippingMethod $method, array $parcel): bool
    {
        $subtotal = (string) $parcel['subtotal'];
        $weight   = (string) $parcel['weight'];

        return ($method->min_order === null || bccomp($subtotal, (string) $method->min_order, 2) >= 0)
            && ($method->max_order === null || bccomp($subtotal, (string) $method->max_order, 2) <= 0)
            && ($method->min_weight === null || bccomp($weight, (string) $method->min_weight, 3) >= 0)
            && ($method->max_weight === null || bccomp($weight, (string) $method->max_weight, 3) <= 0)
            && ($method->category_ids === null || array_diff(array_map(fn ($id) => $id ?? 0, $parcel['categories']), $method->category_ids) === []);
    }

    /** $method's price for $parcel - null when quoted after the order, false when no band takes it. */
    private function priced(ShippingMethod $method, array $parcel): string|null|false
    {
        return match ($method->kind) {
            ShippingMethod::FREE      => '0.00',
            ShippingMethod::QUOTATION => null,
            ShippingMethod::WEIGHT    => $this->band($method->rates ?? [], (string) $parcel['weight']),
            ShippingMethod::ORDER_VALUE => $this->band($method->rates ?? [], (string) $parcel['subtotal']),
            ShippingMethod::QUANTITY  => $this->band($method->rates ?? [], (string) $parcel['quantity']),
            default                   => (string) $method->price,
        };
    }

    /** The price of the highest of $rates $value reaches - false for none. */
    private function band(array $rates, string $value): string|false
    {
        $price = false;

        foreach ($rates as $rate) {
            if (bccomp($value, (string) $rate['from'], 3) >= 0) {
                $price = (string) $rate['price'];
            }
        }

        return $price;
    }

    /** Whether $name is among $names - any, when there are none - whatever its case. */
    private function named(?array $names, ?string $name): bool
    {
        if ($names === null) {
            return true;
        }

        $name = mb_strtolower(trim((string) $name));

        return $name !== '' && in_array($name, array_map(fn (string $kept) => mb_strtolower($kept), $names), true);
    }

    /** Whether $code is among $codes - any, when there are none - whatever its spaces and case; "12*" holds every code starting 12. */
    private function postal(?array $codes, ?string $code): bool
    {
        if ($codes === null) {
            return true;
        }

        $code = strtoupper(str_replace(' ', '', (string) $code));

        foreach ($codes as $kept) {
            $kept = str_replace(' ', '', $kept);

            if ($code !== '' && (str_ends_with($kept, '*') ? str_starts_with($code, rtrim($kept, '*')) : $code === $kept)) {
                return true;
            }
        }

        return false;
    }
}
