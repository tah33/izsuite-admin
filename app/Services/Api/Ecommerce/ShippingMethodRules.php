<?php

namespace App\Services\Api\Ecommerce;

use App\Models\User\ShippingMethod;
use App\Models\User\ShippingZone;
use App\Models\User\Workspace;
use App\Repositories\User\ShippingRepository;
use Illuminate\Validation\ValidationException;

/**
 * What a shipping method keeps (Doc 19 section 19.26 - Phase D1), checked:
 *
 * - its name its own in its zone;
 * - a flat rate or local delivery its price; by weight, by order value or by
 *   quantity its bands, each from a value at a price, once each, kept from
 *   the lowest; free and quoted after the order neither;
 * - its tax a sales tax rate of the business that is on - or the one it has
 *   already - or none, and none while the business has taxes off;
 * - its delivery days, order values and weights each no more than their
 *   most;
 * - the categories it carries the business's - none for every product.
 */
class ShippingMethodRules
{
    public function __construct(
        protected ShippingRepository $shipping,
    ) {}

    public function checked(Workspace $workspace, ShippingZone $zone, array $data, ?ShippingMethod $method = null): array
    {
        $name = trim($data['name']);
        $kind = $data['kind'];

        if ($this->shipping->methodNameTaken($zone, $name, $method?->shipping_zone_id === $zone->id ? $method->id : null)) {
            throw ValidationException::withMessages(['name' => "Another method of {$zone->name} has this name."]);
        }

        $this->inOrder($data, 'days_min', 'days_max', 'The most days are fewer than the fewest.');
        $this->inOrder($data, 'min_order', 'max_order', 'The highest order value is below the lowest.');
        $this->inOrder($data, 'min_weight', 'max_weight', 'The heaviest weight is below the lightest.');

        return [
            'shipping_zone_id' => $zone->id,
            'name'             => $name,
            'kind'             => $kind,
            'price'            => in_array($kind, ShippingMethod::PRICED, true) ? $this->price($data) : null,
            'rates'            => in_array($kind, ShippingMethod::BANDED, true) ? $this->rates($data['rates'] ?? []) : null,
            'tax_rate_id'      => $this->taxRate($workspace, $data, $method),
            'days_min'         => $data['days_min'] ?? null,
            'days_max'         => $data['days_max'] ?? null,
            'min_order'        => $data['min_order'] ?? null,
            'max_order'        => $data['max_order'] ?? null,
            'min_weight'       => $data['min_weight'] ?? null,
            'max_weight'       => $data['max_weight'] ?? null,
            'category_ids'     => $this->categories($workspace, $data['category_ids'] ?? []),
            'is_active'        => (bool) ($data['is_active'] ?? true),
        ];
    }

    private function price(array $data): string
    {
        if (! isset($data['price'])) {
            throw ValidationException::withMessages(['price' => 'Give its price.']);
        }

        return (string) $data['price'];
    }

    /** Its bands from the lowest, each from a value once. */
    private function rates(array $rates): array
    {
        if ($rates === []) {
            throw ValidationException::withMessages(['rates' => 'Give at least one rate.']);
        }

        usort($rates, fn (array $a, array $b) => bccomp((string) $a['from'], (string) $b['from'], 3));
        $kept = [];

        foreach ($rates as $rate) {
            $from = bcadd((string) $rate['from'], '0', 3);

            if (isset($kept[$from])) {
                throw ValidationException::withMessages(['rates' => 'Two rates start at '.rtrim(rtrim($from, '0'), '.').': give each its own start.']);
            }

            $kept[$from] = ['from' => $from, 'price' => bcadd((string) $rate['price'], '0', 2)];
        }

        return array_values($kept);
    }

    private function taxRate(Workspace $workspace, array $data, ?ShippingMethod $method): ?int
    {
        $id = isset($data['tax_rate_id']) ? (int) $data['tax_rate_id'] : null;

        if ($id === null || ! $workspace->usesTaxes()) {
            return null;
        }

        if ($id !== $method?->tax_rate_id && ! $this->shipping->isSalesTaxRate($workspace, $id)) {
            throw ValidationException::withMessages(['tax_rate_id' => 'Choose one of your sales tax rates that is on.']);
        }

        return $id;
    }

    /** The business's categories among $ids, once each - none for every product. */
    private function categories(Workspace $workspace, array $ids): ?array
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));

        if ($ids === []) {
            return null;
        }

        $known = $this->shipping->categoryIds($workspace, $ids);

        if (count($known) !== count($ids)) {
            throw ValidationException::withMessages(['category_ids' => 'Choose among your item categories.']);
        }

        sort($known);

        return $known;
    }

    private function inOrder(array $data, string $least, string $most, string $message): void
    {
        if (isset($data[$least], $data[$most]) && bccomp((string) $data[$least], (string) $data[$most], 3) > 0) {
            throw ValidationException::withMessages([$most => $message]);
        }
    }
}
