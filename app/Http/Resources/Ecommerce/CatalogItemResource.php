<?php

namespace App\Http\Resources\Ecommerce;

use App\Models\User\Item;
use App\Services\Files\FileStorage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A row of the Catalog page (Doc 19 Phase B1): an item for sale - its name,
 * number, SKU, type, category, sales price and thumbnail - and its online
 * listing, when it has one, with its status on the business's day $today.
 *
 * @property Item $resource
 */
class CatalogItemResource extends JsonResource
{
    public function __construct(Item $item, private string $today)
    {
        parent::__construct($item);
    }

    public function toArray(Request $request): array
    {
        $product = $this->catalogProduct;
        // The item's own image, else its listing's first picture.
        $picture = $this->image ?? $product?->cover;
        // Phase B3: the listing it is a variant of, when that is another item's.
        $listing = $this->catalogVariant?->product;
        $listing = $listing !== null && $listing->item_id !== $this->id ? $listing : null;

        return [
            'id'            => $this->id,
            'name'          => $this->name,
            'item_number'   => $this->item_number,
            'sku'           => $this->sku,
            'type'          => $this->type,
            'is_active'     => (bool) $this->is_active,
            'category'      => $this->category ? ['id' => $this->category->id, 'name' => $this->category->name] : null,
            'sale_price'    => $this->sale_price,
            'thumbnail_url' => $picture ? app(FileStorage::class)->url($picture, true) : null,
            'variant_of'    => $listing ? ['item_id' => $listing->item_id, 'title' => $listing->title] : null,
            'product'       => $product ? [
                'id'              => $product->id,
                'title'           => $product->title,
                'slug'            => $product->slug,
                'status'          => $product->statusOn($this->today),
                'visibility'      => $product->visibility,
                'sales_method'    => $product->sales_method,
                'availability'    => $product->availability,
                'stock_quantity'  => $product->stock_quantity,
                'available_from'  => $product->available_from?->toDateString(),
                'available_until' => $product->available_until?->toDateString(),
                // Phase B3: how many variants it groups - each then with its own availability.
                'variant_count'   => (int) ($product->variants_count ?? 0),
            ] : null,
        ];
    }
}
