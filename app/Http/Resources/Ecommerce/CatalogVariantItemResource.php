<?php

namespace App\Http\Resources\Ecommerce;

use App\Models\User\Item;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * An item a listing may group as a variant (Doc 19 Phase B3), as its
 * variants' list offers it: its name, number, SKU, sales price and whether
 * it is active.
 *
 * @property Item $resource
 */
class CatalogVariantItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'name'        => $this->name,
            'item_number' => $this->item_number,
            'sku'         => $this->sku,
            'sale_price'  => $this->sale_price,
            'is_active'   => (bool) $this->is_active,
        ];
    }
}
