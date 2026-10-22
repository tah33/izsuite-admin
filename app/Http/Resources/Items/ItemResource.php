<?php

namespace App\Http\Resources\Items;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ItemResource extends JsonResource
{
    /**
     * Prices go out as strings with two decimals ("12.50"), exactly as the
     * columns hold them - never as floats, which cannot hold every cent.
     */
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'type'           => $this->type,
            'name'           => $this->name,
            'sku'            => $this->sku,
            'unit'           => $this->unit,
            'sale_price'     => $this->sale_price,
            'purchase_price' => $this->purchase_price,
            'description'    => $this->description,
            'is_active'      => $this->is_active,
        ];
    }
}
