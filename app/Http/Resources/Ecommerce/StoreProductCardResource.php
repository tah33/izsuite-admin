<?php

namespace App\Http\Resources\Ecommerce;

use App\Models\User\CatalogProduct;
use App\Models\User\CatalogProductVariant;
use App\Services\Api\Ecommerce\StorefrontPricing;
use App\Services\Files\FileStorage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A product in a storefront's list (Doc 19 Phase C1): its title, link, short
 * description, brand and picture - its item's image, else its first - how it
 * sells, whether any of it is in stock, and its lowest price as $terms show
 * it (StorefrontPricing) with whether its variants' prices differ.
 *
 * @property CatalogProduct $resource
 */
class StoreProductCardResource extends JsonResource
{
    public function __construct(CatalogProduct $product, private array $terms)
    {
        parent::__construct($product);
    }

    public function toArray(Request $request): array
    {
        $item    = $this->item;
        $picture = $item->image ?? $this->cover;
        $from    = app(StorefrontPricing::class)->fromPrice($this->resource, $this->terms);
        $stocked = $this->variants->isNotEmpty()
            ? $this->variants->contains(fn (CatalogProductVariant $variant) => $variant->availability !== 'out_of_stock')
            : $this->availability !== 'out_of_stock';

        return [
            'title'             => $this->title,
            'slug'              => $this->slug,
            'short_description' => $this->short_description,
            'brand'             => $item->brand?->name,
            'image_url'         => $picture ? app(FileStorage::class)->url($picture, true) : null,
            'sales_method'      => $this->sales_method,
            'in_stock'          => $stocked,
            'price'             => $from['price'],
            'price_varies'      => $from['varies'],
        ];
    }
}
