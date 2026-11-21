<?php

namespace App\Repositories\User;

use App\Models\User\CatalogCategory;
use App\Models\User\CatalogCollection;
use App\Models\User\CatalogProduct;
use App\Models\User\CatalogProductVariant;
use App\Models\User\Item;
use App\Models\User\ItemBrand;
use App\Models\User\Storefront;
use App\Services\Api\Ecommerce\StoreVisitor;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * A storefront as its customers see it (Doc 19 Phase C1): the store by its
 * code, the listings it shows - searched, narrowed and sorted - one listing
 * by its link, and its menu's categories and the brands of what it shows.
 * From Phase E3, as the request's visitor sees it: listings kept for
 * signed-in customers show to a customer signed in (StoreVisitor). Its
 * sitemap lists those for everyone alone.
 */
class StorefrontCatalogRepository
{
    /** What an item shows on a storefront: its prices are StorefrontPricing's to work out - its purchase price for a price list's markup (Phase D3), never shown. */
    private const ITEM = 'id,name,sku,barcode,unit,sale_price,purchase_price,sales_tax_id,type,combo_pricing,brand_id,category_id,is_active';

    /** What a product card shows. */
    private const CARD_WITH = ['item:'.self::ITEM, 'item.image', 'item.brand:id,name', 'item.components.component:id,sale_price', 'cover'];

    /** The storefronts' sorts: the newest first by default. */
    public const SORTS = ['newest', 'title', 'price_low', 'price_high'];

    public function __construct(
        protected StoreVisitor $visitor,
    ) {}

    public function findStore(string $code): ?Storefront
    {
        return Storefront::query()->where('code', Str::lower($code))->with('workspace')->first();
    }

    /**
     * The listings $storefront shows anyone, as $filters ask - within() - and
     * among $ids when given (Phase C3: those passing the filters by price,
     * options and attributes), sorted. In a collection of products chosen by
     * hand, in its order unless sorted. Each with what its card shows - and
     * the variants it shows.
     */
    public function products(Storefront $storefront, array $filters, string $today, int $perPage, ?CatalogCollection $collection = null, ?array $ids = null): LengthAwarePaginator
    {
        $query = $this->within($storefront, $filters, $today, $collection)
            ->select('catalog_products.*')
            ->with([...self::CARD_WITH, 'variants' => $this->shownVariants(), 'variants.item:'.self::ITEM, 'variants.item.components.component:id,sale_price'])
            ->when($ids !== null, fn (Builder $query) => $query->whereKey($ids));

        if ($collection && $collection->kind !== CatalogCollection::BRAND && ($filters['sort'] ?? null) === null) {
            $query->orderBy(DB::table('catalog_collection_products')->select('position')
                ->whereColumn('catalog_collection_products.catalog_product_id', 'catalog_products.id')->where('catalog_collection_products.catalog_collection_id', $collection->id));
        }

        // By price, its item's: those without one last either way.
        match ($filters['sort'] ?? 'newest') {
            'title'      => $query->orderBy('title'),
            'price_low'  => $query->orderBy($this->itemPrice('case when sale_price is null then 1 else 0 end'))->orderBy($this->itemPrice('sale_price')),
            'price_high' => $query->orderBy($this->itemPrice('case when sale_price is null then 1 else 0 end'))->orderByDesc($this->itemPrice('sale_price')),
            default      => $query->orderByDesc('catalog_products.id'),
        };

        return $query->orderBy('catalog_products.id')->paginate($perPage);
    }

    /**
     * Phase C3: the listings within $filters, oldest first - the first
     * $limit - with their options and attributes, and their items and shown
     * variants as their prices need them: what the filters by price, options
     * and attributes look through.
     */
    public function listingsWithin(Storefront $storefront, array $filters, string $today, ?CatalogCollection $collection, int $limit): Collection
    {
        return $this->within($storefront, $filters, $today, $collection)
            ->with(['item:'.self::ITEM, 'item.components.component:id,sale_price', 'variants' => $this->shownVariants(), 'variants.item:'.self::ITEM, 'variants.item.components.component:id,sale_price'])
            ->orderBy('catalog_products.id')
            ->limit($limit)
            ->get(['catalog_products.id', 'catalog_products.item_id', 'catalog_products.options', 'catalog_products.custom_attributes']);
    }

    /**
     * The listings $storefront shows anyone, as $filters ask: searched by
     * title, keywords, descriptions, SKU, brand or category, narrowed to a
     * category (by its link) or a brand and to those in stock. Within
     * $collection (Phase C2) when given: those it holds, or a brand's.
     */
    private function within(Storefront $storefront, array $filters, string $today, ?CatalogCollection $collection): Builder
    {
        $query = $this->shown($storefront, $today, $this->visitor->listed());

        if ($collection?->kind === CatalogCollection::BRAND) {
            $query->whereHas('item', fn (Builder $item) => $item->where('brand_id', $collection->item_brand_id));
        } elseif ($collection) {
            $query->whereHas('collections', fn (Builder $held) => $held->whereKey($collection->id));
        }

        $query->when($filters['search'] ?? null, function (Builder $query, string $search) {
            $like = '%'.addcslashes($search, '%_\\').'%';

            $query->where(fn (Builder $query) => $query->where('title', 'like', $like)
                ->orWhere('keywords', 'like', $like)
                ->orWhere('short_description', 'like', $like)
                ->orWhere('description', 'like', $like)
                ->orWhereHas('item', fn (Builder $item) => $item->where('sku', 'like', $like)
                    ->orWhereHas('brand', fn (Builder $brand) => $brand->where('name', 'like', $like))
                    ->orWhereHas('category', fn (Builder $category) => $category->where('name', 'like', $like))));
        });

        $query->when($filters['category'] ?? null, fn (Builder $query, string $slug) => $query->whereHas('item.category.catalogCategory', fn (Builder $online) => $online->where('slug', Str::lower($slug))));
        $query->when($filters['brand'] ?? null, fn (Builder $query, $brand) => $query->whereHas('item', fn (Builder $item) => $item->where('brand_id', (int) $brand)));
        // In stock: its own availability - or, with variants, one of those shown.
        $query->when($filters['in_stock'] ?? false, fn (Builder $query) => $query->where(fn (Builder $query) => $query
            ->where(fn (Builder $own) => $own->whereDoesntHave('variants', fn (Builder $variant) => $this->shownVariant($variant))->where('catalog_products.availability', '!=', 'out_of_stock'))
            ->orWhereHas('variants', fn (Builder $variant) => $this->shownVariant($variant)->where('catalog_product_variants.availability', '!=', 'out_of_stock'))));

        return $query;
    }

    /** The listing $storefront shows at $slug: for anyone, or for those with its link. */
    public function product(Storefront $storefront, string $slug, string $today): ?CatalogProduct
    {
        return $this->shown($storefront, $today, $this->visitor->reachable())
            ->where('slug', Str::lower($slug))
            ->with([
                'item:'.self::ITEM, 'item.image', 'item.brand:id,name', 'item.category:id,name', 'item.category.catalogCategory:id,item_category_id,slug',
                'item.components.component:id,sale_price', 'pictures',
                'variants' => $this->shownVariants(), 'variants.item:'.self::ITEM, 'variants.item.image', 'variants.item.components.component:id,sale_price',
            ])
            ->first();
    }

    /**
     * Phase D3: of $ids, the listings $storefront shows on $today - to anyone
     * or at their link - by id, with what a cart's line shows and prices:
     * their items, pictures and the variants they show, with theirs.
     */
    public function shownAmong(Storefront $storefront, array $ids, string $today): Collection
    {
        return $this->shown($storefront, $today, $this->visitor->reachable())
            ->whereKey($ids)
            ->with([
                'item:'.self::ITEM, 'item.image', 'item.components.component:id,sale_price', 'cover',
                'variants' => $this->shownVariants(), 'variants.item:'.self::ITEM, 'variants.item.image', 'variants.item.components.component:id,sale_price',
            ])
            ->get()
            ->keyBy('id');
    }

    /**
     * Phase C6a: the links of the listings $storefront shows anyone on $today,
     * for its sitemap - the first $limit - with when each last changed.
     */
    public function sitemapProducts(Storefront $storefront, string $today, int $limit): Collection
    {
        return $this->shown($storefront, $today, [CatalogProduct::VISIBLE_PUBLIC])->orderBy('catalog_products.id')->limit($limit)->get(['catalog_products.slug', 'catalog_products.updated_at']);
    }

    /** The online categories in the storefront's menu, in their places, with their names. */
    public function menuOf(Storefront $storefront): Collection
    {
        return CatalogCategory::query()
            ->where('workspace_id', $storefront->workspace_id)
            ->where('in_menu', true)
            ->with('category:id,name')
            ->orderBy('position')
            ->orderBy('id')
            ->get(['id', 'item_category_id', 'slug', 'position']);
    }

    /** The brands of the listings the storefront shows anyone, by name. */
    public function brandsOf(Storefront $storefront, string $today): Collection
    {
        return ItemBrand::query()
            ->where('workspace_id', $storefront->workspace_id)
            ->whereHas('items', fn (Builder $item) => $item->whereHas('catalogProduct', fn (Builder $listing) => $this->showing($listing, $storefront, $today, $this->visitor->listed())))
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    /** The listings $storefront shows to $visibilities on $today. */
    private function shown(Storefront $storefront, string $today, array $visibilities): Builder
    {
        return $this->showing(CatalogProduct::query(), $storefront, $today, $visibilities);
    }

    /**
     * $query narrowed to what $storefront shows on $today: the business's
     * listings published - or scheduled, from their first day - not over,
     * for $visibilities, on every storefront or on this one, of an active
     * item it sells.
     */
    private function showing(Builder $query, Storefront $storefront, string $today, array $visibilities): Builder
    {
        return $query
            ->where('catalog_products.workspace_id', $storefront->workspace_id)
            ->whereIn('catalog_products.visibility', $visibilities)
            ->whereIn('catalog_products.status', [CatalogProduct::PUBLISHED, CatalogProduct::SCHEDULED])
            ->where(fn (Builder $query) => $query->whereNull('catalog_products.available_from')->orWhereDate('catalog_products.available_from', '<=', $today))
            ->where(fn (Builder $query) => $query->whereNull('catalog_products.available_until')->orWhereDate('catalog_products.available_until', '>=', $today))
            ->where(fn (Builder $query) => $query->where('catalog_products.all_storefronts', true)->orWhereHas('storefronts', fn (Builder $store) => $store->whereKey($storefront->id)))
            ->whereHas('item', fn (Builder $item) => $item->where('is_active', true)->whereIn('usage', [Item::USAGE_SALES, Item::USAGE_BOTH]));
    }

    /** $expression of a listing's item, to sort by. */
    private function itemPrice(string $expression): Builder
    {
        return Item::query()->selectRaw($expression)->whereColumn('items.id', 'catalog_products.item_id');
    }

    /** A listing's variants a storefront shows, as a relation to load. */
    private function shownVariants(): \Closure
    {
        return fn (HasMany $variants) => $this->shownVariant($variants->getQuery());
    }

    /** $variants narrowed to those a storefront shows: shown, of an active item. */
    private function shownVariant(Builder $variants): Builder
    {
        return $variants->where('catalog_product_variants.status', CatalogProductVariant::ACTIVE)->whereHas('item', fn (Builder $item) => $item->where('is_active', true));
    }
}
