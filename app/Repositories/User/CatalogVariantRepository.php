<?php

namespace App\Repositories\User;

use App\Models\User\CatalogProduct;
use App\Models\User\Item;
use App\Models\User\Workspace;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Listings' options and variants (Doc 19 Phase B3): the items that may be a
 * listing's variants, those being made its variants, and saving them.
 */
class CatalogVariantRepository
{
    /** What a variant shows of its item. */
    private const ITEM_COLUMNS = ['id', 'name', 'item_number', 'sku', 'barcode', 'sale_price', 'is_active', 'type'];

    /**
     * The business's items for sale of $type - the listing's own item's -
     * that may be its variants, by name: its own item, its variants, and
     * items in no listing and of no other listing's variants.
     */
    public function candidatesFor(Workspace $workspace, CatalogProduct $product, string $type, int $limit): Collection
    {
        return $this->forSale($workspace)
            ->where('type', $type)
            ->where(fn (Builder $query) => $query->whereKey($product->item_id)
                ->orWhere(fn (Builder $query) => $query->whereDoesntHave('catalogProduct')
                    ->where(fn (Builder $query) => $query->whereDoesntHave('catalogVariant')
                        ->orWhereHas('catalogVariant', fn (Builder $variant) => $variant->where('catalog_product_id', $product->id)))))
            ->orderBy('name')
            ->orderBy('id')
            ->limit($limit)
            ->get(self::ITEM_COLUMNS);
    }

    /** The business's items for sale among $ids, keyed by id, with any listing of their own and any listing they are a variant of. */
    public function itemsFor(Workspace $workspace, array $ids): Collection
    {
        return $this->forSale($workspace)
            ->whereKey($ids)
            ->with(['catalogProduct:id,item_id', 'catalogVariant:id,item_id,catalog_product_id', 'catalogVariant.product:id,item_id,title'])
            ->get(self::ITEM_COLUMNS)
            ->keyBy('id');
    }

    /**
     * $options kept on the listing - none: null - and $rows as its variants
     * in their order, each by its item: those not among them removed, the
     * others changed or made. Whether anything changed.
     *
     * @param  list<array{item_id: int, option_values: list<string>, status: string, availability: string, stock_quantity: ?string, weight: ?string}>  $rows
     */
    public function save(CatalogProduct $product, ?array $options, array $rows): bool
    {
        $product->options = $options;
        $changed          = $product->isDirty('options');
        $product->save();

        $changed = $product->variants()->whereNotIn('item_id', array_column($rows, 'item_id'))->delete() > 0 || $changed;

        foreach ($rows as $position => $row) {
            $variant = $product->variants()->firstOrNew(['item_id' => $row['item_id']]);
            $variant->fill([...$row, 'position' => $position]);
            $variant->workspace_id ??= $product->workspace_id;
            $changed = $variant->isDirty() || $changed;
            $variant->save();
        }

        $product->unsetRelation('variants');

        return $changed;
    }

    /** Sold, or sold and bought. */
    private function forSale(Workspace $workspace): Builder
    {
        return Item::query()->where('workspace_id', $workspace->id)->whereIn('usage', [Item::USAGE_SALES, Item::USAGE_BOTH]);
    }
}
