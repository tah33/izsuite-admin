<?php

namespace App\Http\Resources\Pos;

use App\Services\Files\FileStorage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * An item as the POS selling screen shows it (Doc 18 Phase C1): its name,
 * SKU, unit, type and category, its picture's thumbnail, and its price at
 * the register for no customer in particular - the cart's quote says where
 * a price comes from. The resource is [item, price, ...].
 */
class PosSellItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $item = $this->resource['item'];

        return [
            'id'           => $item->id,
            'name'         => $item->name,
            'sku'          => $item->sku,
            'type'         => $item->type,
            'unit'         => $item->unit,
            'category'     => $item->category?->name,
            'image'        => $item->image ? app(FileStorage::class)->url($item->image, true) : null,
            'price'        => $this->resource['price'],
        ];
    }
}
