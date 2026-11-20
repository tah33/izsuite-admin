<?php

namespace App\Http\Resources\Ecommerce;

use App\Models\User\CatalogCategory;
use App\Models\User\CatalogCollection;
use App\Models\User\CatalogProduct;
use App\Services\Files\FileStorage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * What a storefront's search suggests as a customer types (Doc 19 section
 * 19.12 - Phase C2): the products, categories and collections whose names
 * match - each its name, link and, for a product, its thumbnail.
 */
class StoreSuggestionsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $storage = app(FileStorage::class);

        return [
            'products' => collect($this->resource['products'])->map(function (CatalogProduct $product) use ($storage) {
                $picture = $product->item->image ?? $product->cover;

                return ['title' => $product->title, 'slug' => $product->slug, 'image_url' => $picture ? $storage->url($picture, true) : null];
            })->values(),
            'categories'  => $this->resource['categories']->map(fn (CatalogCategory $online) => ['name' => $online->category->name, 'slug' => $online->slug])->values(),
            'collections' => $this->resource['collections']->map(fn (CatalogCollection $collection) => ['name' => $collection->name, 'slug' => $collection->slug])->values(),
        ];
    }
}
