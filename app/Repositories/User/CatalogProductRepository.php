<?php

namespace App\Repositories\User;

use App\Models\User\CatalogProduct;
use App\Models\User\CatalogProductVariant;
use App\Models\User\Item;
use App\Models\User\Workspace;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * The catalog (Doc 19 Phase B1): the business's items for sale, each with
 * its online listing when it has one, and those listings.
 */
class CatalogProductRepository
{
    /** What a catalog row shows of its item and listing - the listing's own columns are ROW_LISTING. */
    private const ROW_WITH = [
        'category:id,name', 'image',
        'catalogProduct.cover',
        // Phase B3: the listing it is a variant of.
        'catalogVariant:id,item_id,catalog_product_id', 'catalogVariant.product:id,item_id,title',
    ];

    /** The listing's columns a catalog row shows, and - from Phase B3 - how many variants it groups. */
    private const ROW_LISTING = ['id', 'item_id', 'title', 'slug', 'status', 'visibility', 'sales_method', 'availability', 'stock_quantity', 'available_from', 'available_until'];

    /** What a listing's page shows - from Phase B3, its variants' items too. */
    private const PRODUCT_WITH = [
        'storefronts:storefronts.id,storefronts.name', 'pictures.uploader:id,first_name,last_name',
        'variants.item:id,name,item_number,sku,barcode,sale_price,is_active', 'variants.item.image',
    ];

    /**
     * The business's items for sale - sold, or sold and bought - by name,
     * searched by name, number, SKU or barcode, narrowed to a category and
     * to an online status: "offline" for those not in the catalog, else the
     * listings' status. $today, the business's day, tells a scheduled
     * listing from a published one.
     */
    public function paginateFor(Workspace $workspace, array $filters, int $perPage, string $today): LengthAwarePaginator
    {
        return $this->forSale($workspace)
            ->with(['catalogProduct' => fn ($listing) => $listing->select(self::ROW_LISTING)->withCount('variants'), ...self::ROW_WITH])
            ->when($filters['search'] ?? null, function (Builder $query, string $search) {
                $like = "%{$search}%";
                $query->where(fn (Builder $query) => $query->where('name', 'like', $like)->orWhere('item_number', 'like', $like)->orWhere('sku', 'like', $like)->orWhere('barcode', 'like', $like));
            })
            ->when($filters['category_id'] ?? null, fn (Builder $query, $id) => $query->where('category_id', (int) $id))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $this->withStatus($query, $status, $today))
            ->orderBy('name')
            ->orderBy('id')
            ->paginate($perPage);
    }

    /** One of the business's items for sale, with what its listing's page shows - and, from Phase B3, the listing it is a variant of. */
    public function findItem(Workspace $workspace, int $itemId): ?Item
    {
        return $this->forSale($workspace)->with(['category:id,name', 'brand:id,name', 'image', 'catalogVariant.product:id,item_id,title'])->find($itemId);
    }

    /** The item's listing, with its storefronts and pictures - or null. */
    public function forItem(Item $item): ?CatalogProduct
    {
        return CatalogProduct::query()->where('item_id', $item->id)->with(self::PRODUCT_WITH)->first();
    }

    /** Whether another listing of the business - $exceptId aside - has $slug. */
    public function slugTaken(Workspace $workspace, string $slug, ?int $exceptId = null): bool
    {
        return CatalogProduct::query()->where('workspace_id', $workspace->id)->where('slug', $slug)->when($exceptId, fn (Builder $query, int $id) => $query->whereKeyNot($id))->exists();
    }

    /** Made for the business and item, so neither is read from $data; on $storefrontIds when not every storefront. */
    public function create(Workspace $workspace, Item $item, array $data, array $storefrontIds): CatalogProduct
    {
        $product = new CatalogProduct($data);
        $product->workspace_id = $workspace->id;
        $product->item_id      = $item->id;
        $product->save();

        $product->storefronts()->sync($product->all_storefronts ? [] : $storefrontIds);

        return $product->load(self::PRODUCT_WITH);
    }

    /** Whether anything changed. */
    public function update(CatalogProduct $product, array $data, array $storefrontIds): bool
    {
        $product->update($data);
        $synced = $product->storefronts()->sync($product->all_storefronts ? [] : $storefrontIds);
        $product->load(self::PRODUCT_WITH);

        return $product->wasChanged() || array_filter($synced) !== [];
    }

    public function delete(CatalogProduct $product): void
    {
        $product->delete();
    }

    /** How many pictures it has. */
    public function pictureCount(CatalogProduct $product): int
    {
        return $product->pictures()->count();
    }

    /** The business's storefronts, by name - for a listing to be put on. */
    public function storefrontsOf(Workspace $workspace): Collection
    {
        return $workspace->storefronts()->orderBy('name')->get(['id', 'name', 'code', 'status']);
    }

    /**
     * Doc 19 Phase F2: $workspace's listings $productIds and the variants
     * $variantIds, each with its availability and what is left of it - locked,
     * when $lock, to be counted down - keyed by id.
     *
     * @return array{products: Collection<int, CatalogProduct>, variants: Collection<int, CatalogProductVariant>}
     */
    public function stockOf(Workspace $workspace, array $productIds, array $variantIds, bool $lock): array
    {
        $products = CatalogProduct::query()->where('workspace_id', $workspace->id)->whereIn('id', $productIds)->when($lock, fn (Builder $query) => $query->lockForUpdate())->get()->keyBy('id');

        return [
            'products' => $products,
            'variants' => CatalogProductVariant::query()->whereIn('catalog_product_id', $products->keys())->whereIn('id', $variantIds)->when($lock, fn (Builder $query) => $query->lockForUpdate())->get()->keyBy('id'),
        ];
    }

    /** Doc 19 Phase F2: what is left of a Limited listing or variant, after an order confirmed took its share. */
    public function setLeft(CatalogProduct|CatalogProductVariant $stock, string $left): void
    {
        $stock->update(['stock_quantity' => $left]);
    }

    /** Sold, or sold and bought. */
    private function forSale(Workspace $workspace): Builder
    {
        return Item::query()->where('workspace_id', $workspace->id)->whereIn('usage', [Item::USAGE_SALES, Item::USAGE_BOTH]);
    }

    private function withStatus(Builder $query, string $status, string $today): void
    {
        // Not online: in no listing, and - from Phase B3 - no listing's variant.
        if ($status === 'offline') {
            $query->whereDoesntHave('catalogProduct')->whereDoesntHave('catalogVariant');

            return;
        }

        $query->whereHas('catalogProduct', function (Builder $listing) use ($status, $today) {
            match ($status) {
                CatalogProduct::SCHEDULED => $listing->where('status', CatalogProduct::SCHEDULED)->whereDate('available_from', '>', $today),
                CatalogProduct::PUBLISHED => $listing->where(fn (Builder $query) => $query->where('status', CatalogProduct::PUBLISHED)
                    ->orWhere(fn (Builder $query) => $query->where('status', CatalogProduct::SCHEDULED)->whereDate('available_from', '<=', $today))),
                default => $listing->where('status', $status),
            };
        });
    }
}
