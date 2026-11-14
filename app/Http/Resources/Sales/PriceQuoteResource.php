<?php

namespace App\Http\Resources\Sales;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** A sales form line's price by a price list (Doc 16 Phase F1): the line's key, the price, and the list and rule that gave it. */
class PriceQuoteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'key'                => $this->resource['key'],
            'unit_price'         => $this->resource['unit_price'],
            'price_list_id'      => $this->resource['price_list_id'],
            'price_list_rule_id' => $this->resource['price_list_rule_id'],
            'price_list'         => $this->resource['price_list'],
        ];
    }
}