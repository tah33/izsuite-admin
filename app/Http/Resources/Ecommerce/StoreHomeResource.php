<?php

namespace App\Http\Resources\Ecommerce;

use App\Models\User\CatalogCategory;
use App\Models\User\CatalogCollection;
use App\Models\User\CatalogProduct;
use App\Services\Files\FileStorage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A storefront's home page (Doc 19 Phase C4b): its sections in their order,
 * each with what it shows - a banner's picture, heading, line, button and
 * where it goes; collections as their tiles; the menu's categories; products
 * as their cards, with the collection they are from; and text.
 */
class StoreHomeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $terms   = $this->resource['terms'];
        $storage = app(FileStorage::class);

        return [
            'sections' => array_map(fn (array $section) => match ($section['type']) {
                'banner'      => [
                    'type'      => 'banner',
                    'image_url' => $section['picture'] ? $storage->url($section['picture']) : null,
                    'heading'   => $section['heading'],
                    'text'      => $section['text'],
                    'button'    => $section['button'],
                    'link'      => $section['link'],
                ],
                'collections' => [
                    'type'        => 'collections',
                    'heading'     => $section['heading'],
                    'collections' => $section['collections']->map(fn (CatalogCollection $collection) => (new StoreCollectionResource($collection))->resolve($request))->values(),
                ],
                'categories'  => [
                    'type'       => 'categories',
                    'heading'    => $section['heading'],
                    'categories' => $section['categories']->map(fn (CatalogCategory $online) => ['name' => $online->category->name, 'slug' => $online->slug])->values(),
                ],
                'products'    => [
                    'type'       => 'products',
                    'heading'    => $section['heading'],
                    'collection' => $section['collection']?->slug,
                    'products'   => array_map(fn (CatalogProduct $product) => (new StoreProductCardResource($product, $terms))->resolve($request), $section['products']),
                ],
                'text'        => ['type' => 'text', 'heading' => $section['heading'], 'body' => $section['body']],
            }, $this->resource['sections']),
        ];
    }
}
