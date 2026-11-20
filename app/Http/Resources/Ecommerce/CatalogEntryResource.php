<?php

namespace App\Http\Resources\Ecommerce;

use App\Http\Resources\Files\WorkspaceFileResource;
use App\Models\User\CatalogProduct;
use App\Models\User\CatalogProductVariant;
use App\Services\Files\FileStorage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * An item's page in the catalog (Doc 19 Phase B1): the item as its listing
 * starts from - name, number, SKU, type, category, brand, sales price and
 * description, its image - its online listing whole, or null while it is not
 * in the catalog, the business's storefronts it may be put on, and today,
 * the business's day.
 */
class CatalogEntryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $item    = $this->resource['item'];
        $product = $this->resource['product'];
        $today   = $this->resource['today'];
        $storage = app(FileStorage::class);

        return [
            'item' => [
                'id'                => $item->id,
                'name'              => $item->name,
                'item_number'       => $item->item_number,
                'sku'               => $item->sku,
                'type'              => $item->type,
                'is_active'         => (bool) $item->is_active,
                'category'          => $item->category ? ['id' => $item->category->id, 'name' => $item->category->name] : null,
                'brand'             => $item->brand ? ['id' => $item->brand->id, 'name' => $item->brand->name] : null,
                'sale_price'        => $item->sale_price,
                'sales_description' => $item->sales_description,
                'image_url'         => $item->image ? $storage->url($item->image, true) : null,
            ],
            'product'     => $product ? $this->product($product, $today, $request) : null,
            // Phase B3: the listing the item is a variant of - its page is then that listing's.
            'variant_of'  => ($listing = $this->resource['variant_of'] ?? null) ? ['item_id' => $listing->item_id, 'title' => $listing->title] : null,
            'storefronts' => $this->resource['storefronts']->map(fn ($storefront) => ['id' => $storefront->id, 'name' => $storefront->name, 'code' => $storefront->code, 'status' => $storefront->status])->values(),
            'today'       => $today,
        ];
    }

    private function product(CatalogProduct $product, string $today, Request $request): array
    {
        return [
            'id'                => $product->id,
            'title'             => $product->title,
            'slug'              => $product->slug,
            'short_description' => $product->short_description,
            'description'       => $product->description,
            'keywords'          => $product->keywords,
            'status'            => $product->status,
            'status_now'        => $product->statusOn($today),
            'visibility'        => $product->visibility,
            'available_from'    => $product->available_from?->toDateString(),
            'available_until'   => $product->available_until?->toDateString(),
            'sales_method'      => $product->sales_method,
            'availability'      => $product->availability,
            'stock_quantity'    => $product->stock_quantity,
            'weight'            => $product->weight,
            'weight_unit'       => $product->weight_unit,
            'video_urls'        => $product->video_urls ?? [],
            'custom_attributes' => $product->custom_attributes ?? [],
            'options'           => $product->options ?? [],
            'variants'          => $product->variants->map(fn (CatalogProductVariant $variant) => $this->variant($variant))->values(),
            'seo_title'         => $product->seo_title,
            'seo_description'   => $product->seo_description,
            'all_storefronts'   => $product->all_storefronts,
            'storefront_ids'    => $product->storefronts->pluck('id')->values(),
            'pictures'          => WorkspaceFileResource::collection($product->pictures)->resolve($request),
        ];
    }

    /** Phase B3: a variant - its item as Items keeps it, and what the listing adds. */
    private function variant(CatalogProductVariant $variant): array
    {
        $item = $variant->item;

        return [
            'item' => [
                'id'            => $item->id,
                'name'          => $item->name,
                'item_number'   => $item->item_number,
                'sku'           => $item->sku,
                'barcode'       => $item->barcode,
                'sale_price'    => $item->sale_price,
                'is_active'     => (bool) $item->is_active,
                'thumbnail_url' => $item->image ? app(FileStorage::class)->url($item->image, true) : null,
            ],
            'option_values'  => $variant->option_values,
            'status'         => $variant->status,
            'availability'   => $variant->availability,
            'stock_quantity' => $variant->stock_quantity,
            'weight'         => $variant->weight,
        ];
    }
}
