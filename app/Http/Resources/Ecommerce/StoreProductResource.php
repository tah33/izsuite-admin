<?php

namespace App\Http\Resources\Ecommerce;

use App\Models\User\CatalogProduct;
use App\Models\User\CatalogProductVariant;
use App\Models\User\WorkspaceFile;
use App\Services\Api\Ecommerce\StorefrontPricing;
use App\Services\Files\FileStorage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A product's page on a storefront (Doc 19 Phase C1): its words, brand and
 * category, how it sells, its availability and price as $terms show it
 * (StorefrontPricing), its pictures - its item's image first - and video
 * links, its attributes, and its options with the variants shown, each with
 * its values, SKU, price, availability and picture. Its SEO title and
 * description, else its title and short description.
 *
 * @property CatalogProduct $resource
 */
class StoreProductResource extends JsonResource
{
    public function __construct(CatalogProduct $product, private array $terms)
    {
        parent::__construct($product);
    }

    public function toArray(Request $request): array
    {
        $item    = $this->item;
        $pricing = app(StorefrontPricing::class);
        $online  = $item->category?->catalogCategory;

        return [
            'title'             => $this->title,
            'slug'              => $this->slug,
            'short_description' => $this->short_description,
            'description'       => $this->description,
            'brand'             => $item->brand?->name,
            'category'          => $item->category ? ['name' => $item->category->name, 'slug' => $online?->slug] : null,
            'sku'               => $item->sku,
            'sales_method'      => $this->sales_method,
            'availability'      => $this->availability,
            'stock_quantity'    => $this->stock_quantity,
            'price'             => $pricing->priceOf($item, $this->terms),
            'images'            => collect([$item->image])->merge($this->pictures)->filter()->map(fn (WorkspaceFile $file) => $this->image($file))->values(),
            'video_urls'        => $this->video_urls ?? [],
            'attributes'        => $this->custom_attributes ?? [],
            'options'           => $this->variants->isNotEmpty() ? ($this->options ?? []) : [],
            'variants'          => $this->variants->map(fn (CatalogProductVariant $variant) => [
                'id'             => $variant->id,
                'option_values'  => $variant->option_values,
                'sku'            => $variant->item->sku,
                'price'          => $pricing->priceOf($variant->item, $this->terms),
                'availability'   => $variant->availability,
                'stock_quantity' => $variant->stock_quantity,
                'image'          => $variant->item->image ? $this->image($variant->item->image) : null,
            ])->values(),
            'seo'               => ['title' => $this->seo_title ?? $this->title, 'description' => $this->seo_description ?? $this->short_description],
            // Phase C6a: in its lists and search for anyone - else kept for its link, or (Phase E3) for signed-in
            // customers, and for no search engine.
            'listed'            => $this->visibility === CatalogProduct::VISIBLE_PUBLIC,
        ];
    }

    /** @return array{url: string, thumbnail_url: ?string} */
    private function image(WorkspaceFile $file): array
    {
        $storage = app(FileStorage::class);

        return ['url' => $storage->url($file), 'thumbnail_url' => $storage->url($file, true)];
    }
}
