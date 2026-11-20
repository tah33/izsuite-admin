<?php

namespace App\Services\Api\Ecommerce;

use App\Models\User\CatalogProduct;
use App\Models\User\CatalogProductVariant;
use App\Models\User\Item;
use App\Models\User\Workspace;
use App\Repositories\User\CatalogProductRepository;
use App\Repositories\User\CatalogVariantRepository;
use App\Services\Shared\ActivityLogService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * A listing's options and variants (Doc 19 section 19.10 - Phase B3).
 *
 * Up to three options, each named once, with its values, each once. With
 * options, the listing groups existing items as its variants: its own item
 * among them, then others for sale of the same type that have no listing of
 * their own and are no other listing's variants. Each variant has a value of
 * every option, and no two the same ones; a limited one says how many are
 * left. While the listing is published or scheduled, a variant shown is of
 * an active item, priced when sold at its price. Without options, it has no
 * variants. Each change is logged. A variant's SKU, barcode, price and image
 * are its item's, unique as Items keeps them.
 */
class CatalogVariantService
{
    /** How many items the variants' list offers. */
    private const CANDIDATES = 500;

    public function __construct(
        protected CatalogProductRepository $catalog,
        protected CatalogVariantRepository $variants,
        protected CatalogService $catalogService,
    ) {}

    /** The items that may be the listing's variants. */
    public function items(Workspace $workspace, int $itemId): Collection
    {
        [$item, $product] = $this->listing($workspace, $itemId);

        return $this->variants->candidatesFor($workspace, $product, $item->type, self::CANDIDATES);
    }

    /**
     * $data is validated (see SaveCatalogVariantsRequest): the listing's
     * options and variants saved, and its page as it now is.
     *
     * @return array{entry: array, removed: bool}
     */
    public function save(Workspace $workspace, int $itemId, array $data): array
    {
        [$item, $product] = $this->listing($workspace, $itemId);
        $options = $this->options($data['options']);
        $rows    = $options === [] ? $this->none($data['variants']) : $this->rows($workspace, $item, $product, $options, $data['variants']);
        $had     = ($product->options ?? []) !== [];

        if (DB::transaction(fn () => $this->variants->save($product, $options ?: null, $rows))) {
            $description = match (true) {
                $options === [] => "Removed the options and variants of the online product {$this->quoted($product->title)}",
                $had            => "Updated the options and variants of the online product {$this->quoted($product->title)}",
                default         => "Gave the online product {$this->quoted($product->title)} options and variants",
            };

            ActivityLogService::record('updated', $description, $product, [
                'source'   => 'api',
                'options'  => array_column($options, 'name'),
                'variants' => count($rows),
            ]);
        }

        return ['entry' => $this->catalogService->show($workspace, $itemId), 'removed' => $options === []];
    }

    /**
     * The options as kept: names and values trimmed, a value left blank
     * dropped - each name, and each value of an option, once.
     *
     * @return list<array{name: string, values: list<string>}>
     */
    private function options(array $given): array
    {
        $options = [];

        foreach (array_values($given) as $index => $option) {
            $name   = trim($option['name']);
            $values = array_values(array_filter(array_map('trim', $option['values']), fn (string $value) => $value !== ''));

            if (in_array(Str::lower($name), array_map(fn (array $kept) => Str::lower($kept['name']), $options), true)) {
                throw ValidationException::withMessages(["options.{$index}.name" => 'Another option has this name.']);
            }

            if ($values === []) {
                throw ValidationException::withMessages(["options.{$index}.values" => 'Give the option its values.']);
            }

            if (count(array_unique(array_map(fn (string $value) => Str::lower($value), $values))) !== count($values)) {
                throw ValidationException::withMessages(["options.{$index}.values" => 'Give each value once.']);
            }

            $options[] = ['name' => $name, 'values' => $values];
        }

        return $options;
    }

    /** Without options, no variants. */
    private function none(array $variants): array
    {
        if ($variants !== []) {
            throw ValidationException::withMessages(['variants' => 'Name its options first: without them it has no variants.']);
        }

        return [];
    }

    /**
     * The variants as kept, in their order, each checked as the class says.
     *
     * @param  list<array{name: string, values: list<string>}>  $options
     */
    private function rows(Workspace $workspace, Item $item, CatalogProduct $product, array $options, array $given): array
    {
        $given = array_values($given);
        $items = $this->variants->itemsFor($workspace, array_map(fn (array $variant) => (int) $variant['item_id'], $given));
        $shown = in_array($product->status, [CatalogProduct::SCHEDULED, CatalogProduct::PUBLISHED], true);
        $rows  = [];
        $seen  = [];

        if (! in_array($item->id, array_map(fn (array $variant) => (int) $variant['item_id'], $given), true)) {
            throw ValidationException::withMessages(['variants' => "Its own item, {$this->quoted($item->name)}, is one of its variants."]);
        }

        foreach ($given as $index => $variant) {
            $fail    = fn (string $field, string $message) => throw ValidationException::withMessages(["variants.{$index}.{$field}" => $message]);
            $variety = $items->get((int) $variant['item_id']) ?? $fail('item_id', 'Choose among your items for sale.');

            if ($variety->type !== $item->type) {
                $fail('item_id', 'A variant is an item of the same type as its own item.');
            }

            if ($variety->id !== $item->id && $variety->catalogProduct !== null) {
                $fail('item_id', 'This item has a listing of its own: take it out of the catalog first.');
            }

            if ($variety->catalogVariant !== null && $variety->catalogVariant->catalog_product_id !== $product->id) {
                $fail('item_id', "This item is a variant of {$this->quoted($variety->catalogVariant->product->title)}.");
            }

            $values = $this->values($options, array_values($variant['option_values'])) ?? $fail('option_values', 'Choose a value of each option.');
            $key    = Str::lower(implode("\u{1F}", $values));

            if (isset($seen[$key])) {
                $fail('option_values', 'Another variant has these options.');
            }

            $seen[$key] = true;
            $limited    = $variant['availability'] === CatalogProduct::LIMITED;

            if ($limited && ($variant['stock_quantity'] ?? null) === null) {
                $fail('stock_quantity', 'Say how many are left to sell.');
            }

            if ($shown && $variant['status'] === CatalogProductVariant::ACTIVE) {
                if (! $variety->is_active) {
                    $fail('item_id', 'The item is switched off: switch it on in Items, or hide the variant.');
                }

                if ($product->sales_method === CatalogProduct::FIXED_PRICE && $variety->sale_price === null) {
                    $fail('item_id', 'The item has no sales price: give it one in Items, or hide the variant.');
                }
            }

            $rows[] = [
                'item_id'        => $variety->id,
                'option_values'  => $values,
                'status'         => $variant['status'],
                'availability'   => $variant['availability'],
                'stock_quantity' => $limited ? $variant['stock_quantity'] : null,
                'weight'         => $variant['weight'] ?? null,
            ];
        }

        return $rows;
    }

    /**
     * $given as values of $options, in their order and spelt as the options
     * spell them - or null when it is not one value of each.
     */
    private function values(array $options, array $given): ?array
    {
        if (count($given) !== count($options)) {
            return null;
        }

        $values = [];

        foreach ($options as $index => $option) {
            $match = collect($option['values'])->first(fn (string $value) => Str::lower($value) === Str::lower(trim($given[$index])));

            if ($match === null) {
                return null;
            }

            $values[] = $match;
        }

        return $values;
    }

    /** @return array{0: Item, 1: CatalogProduct} */
    private function listing(Workspace $workspace, int $itemId): array
    {
        $item = $this->catalog->findItem($workspace, $itemId) ?? abort(404, 'There is no such item for sale.');

        return [$item, $this->catalog->forItem($item) ?? abort(404, 'This item is not in the catalog.')];
    }

    private function quoted(string $text): string
    {
        return '"'.Str::limit($text, 100).'"';
    }
}
