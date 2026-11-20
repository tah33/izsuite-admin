<?php

namespace App\Services\Api\Ecommerce;

use App\Models\User\CatalogProduct;
use App\Models\User\CatalogProductVariant;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * A storefront's filters by price, options and attributes (Doc 19 section
 * 19.11 - Phase C3), worked out from the listings it shows - with their
 * shown variants and their items - rather than in the database, so a price
 * is filtered as StorefrontPricing shows it, whatever its tax or currency.
 *
 * Each filter offers what those listings have: the lowest and highest price
 * shown, each option's values (by its name, across listings) and each
 * attribute's values. A listing passes a price range when its own price -
 * or, with variants, one of theirs - is within it; options when one of its
 * variants has every value asked for; attributes when it has every one asked
 * for. Names and values are matched without regard to case.
 */
class StorefrontFacets
{
    public function __construct(
        protected StorefrontPricing $pricing,
    ) {}

    /**
     * What $listings offer to filter by.
     *
     * @return array{price: ?array{min: string, max: string}, options: list<array{name: string, values: list<string>}>, attributes: list<array{name: string, values: list<string>}>}
     */
    public function of(Collection $listings, array $terms): array
    {
        $prices     = [];
        $options    = [];
        $attributes = [];

        foreach ($listings as $listing) {
            array_push($prices, ...$this->pricesOf($listing, $terms));

            foreach ($listing->options ?? [] as $at => $option) {
                foreach ($listing->variants as $variant) {
                    $this->offer($options, $option['name'], $variant->option_values[$at] ?? null);
                }
            }

            foreach ($listing->custom_attributes ?? [] as $attribute) {
                $this->offer($attributes, $attribute['name'], $attribute['value']);
            }
        }

        usort($prices, fn (string $a, string $b) => bccomp($a, $b, 2));

        return [
            'price'      => $prices === [] ? null : ['min' => $prices[0], 'max' => end($prices)],
            'options'    => $this->listed($options),
            'attributes' => $this->listed($attributes),
        ];
    }

    /**
     * The ids of $listings that pass $filters' price range ('price_min',
     * 'price_max'), options ('option' => [name => value]) and attributes
     * ('attribute' => [name => value]).
     *
     * @return list<int>
     */
    public function passing(Collection $listings, array $filters, array $terms): array
    {
        $min        = isset($filters['price_min']) ? (string) $filters['price_min'] : null;
        $max        = isset($filters['price_max']) ? (string) $filters['price_max'] : null;
        $options    = $this->lowered($filters['option'] ?? []);
        $attributes = $this->lowered($filters['attribute'] ?? []);

        return $listings
            ->filter(fn (CatalogProduct $listing) => ($min === null && $max === null) || collect($this->pricesOf($listing, $terms))->contains(fn (string $price) => ($min === null || bccomp($price, $min, 2) >= 0) && ($max === null || bccomp($price, $max, 2) <= 0)))
            ->filter(fn (CatalogProduct $listing) => $options === [] || $this->hasOptions($listing, $options))
            ->filter(fn (CatalogProduct $listing) => $attributes === [] || $this->hasAttributes($listing, $attributes))
            ->map(fn (CatalogProduct $listing) => (int) $listing->id)
            ->values()
            ->all();
    }

    /** @return list<string> its own price - or, with variants, theirs - as $terms show them */
    private function pricesOf(CatalogProduct $listing, array $terms): array
    {
        $items = $listing->variants->isNotEmpty() ? $listing->variants->map(fn (CatalogProductVariant $variant) => $variant->item) : collect([$listing->item]);

        return $items->map(fn ($item) => $this->pricing->priceOf($item, $terms))->filter(fn (?string $price) => $price !== null)->values()->all();
    }

    /** Whether one of its variants has each value of $wanted (lowered name => lowered value). */
    private function hasOptions(CatalogProduct $listing, array $wanted): bool
    {
        $at = collect($listing->options ?? [])->mapWithKeys(fn (array $option, int $index) => [Str::lower($option['name']) => $index]);

        if (array_diff_key($wanted, $at->all()) !== []) {
            return false;
        }

        return $listing->variants->contains(fn (CatalogProductVariant $variant) => collect($wanted)->every(fn (string $value, string $name) => Str::lower($variant->option_values[$at[$name]] ?? '') === $value));
    }

    /** Whether it has each attribute of $wanted (lowered name => lowered value). */
    private function hasAttributes(CatalogProduct $listing, array $wanted): bool
    {
        $has = collect($listing->custom_attributes ?? [])->mapWithKeys(fn (array $attribute) => [Str::lower($attribute['name']) => Str::lower($attribute['value'])]);

        return collect($wanted)->every(fn (string $value, string $name) => ($has[$name] ?? null) === $value);
    }

    /** $value offered under $name - each name, and each of its values, once, as first spelt. */
    private function offer(array &$offered, ?string $name, ?string $value): void
    {
        if ($name === null || $value === null || trim($name) === '' || trim($value) === '') {
            return;
        }

        $key = Str::lower(trim($name));
        $offered[$key] ??= ['name' => trim($name), 'values' => []];
        $offered[$key]['values'][Str::lower(trim($value))] ??= trim($value);
    }

    /** @return list<array{name: string, values: list<string>}> by name */
    private function listed(array $offered): array
    {
        ksort($offered);

        return array_values(array_map(fn (array $offer) => ['name' => $offer['name'], 'values' => array_values($offer['values'])], $offered));
    }

    /** @return array<string, string> $asked by lowered name, lowered values - those left blank dropped */
    private function lowered(array $asked): array
    {
        return collect($asked)
            ->mapWithKeys(fn ($value, $name) => [Str::lower(trim((string) $name)) => Str::lower(trim((string) $value))])
            ->filter(fn (string $value, string $name) => $name !== '' && $value !== '')
            ->all();
    }
}
