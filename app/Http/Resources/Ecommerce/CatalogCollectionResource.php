<?php

namespace App\Http\Resources\Ecommerce;

use App\Models\User\CatalogCollection;
use App\Services\Files\FileStorage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A collection (Doc 19 Phase B2) as the Catalog page's Collections show and
 * edit it: its link, kind, brand, words, status, days, SEO and place, its
 * picture, and its products - those chosen, in their order, or how many of
 * its brand's are listed, as $brandListings counts them.
 *
 * @property CatalogCollection $resource
 */
class CatalogCollectionResource extends JsonResource
{
    /** @param array<int, int> $brandListings brand id => its listings */
    public function __construct(CatalogCollection $collection, private array $brandListings = [])
    {
        parent::__construct($collection);
    }

    public function toArray(Request $request): array
    {
        $byBrand = $this->kind === CatalogCollection::BRAND;

        return [
            'id'              => $this->id,
            'name'            => $this->name,
            'slug'            => $this->slug,
            'kind'            => $this->kind,
            'brand'           => $this->brand ? ['id' => $this->brand->id, 'name' => $this->brand->name] : null,
            'description'     => $this->description,
            'status'          => $this->status,
            'available_from'  => $this->available_from?->toDateString(),
            'available_until' => $this->available_until?->toDateString(),
            'seo_title'       => $this->seo_title,
            'seo_description' => $this->seo_description,
            'position'        => $this->position,
            'image_url'       => $this->image ? app(FileStorage::class)->url($this->image, true) : null,
            'products'        => $byBrand ? [] : $this->products->map(fn ($product) => ['id' => $product->id, 'title' => $product->title])->values(),
            'product_count'   => $byBrand ? ($this->brandListings[$this->item_brand_id] ?? 0) : $this->products->count(),
        ];
    }
}
