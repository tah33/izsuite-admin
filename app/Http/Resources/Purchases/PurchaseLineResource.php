<?php

namespace App\Http\Resources\Purchases;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** One line of a purchase, as its page and edit form show it - its tax as it was kept, or null for none. */
class PurchaseLineResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id,
            'item_id'         => $this->item_id,
            'name'            => $this->name,
            'description'     => $this->description,
            'quantity'        => $this->quantity,
            'unit'            => $this->unit,
            'unit_price'      => $this->unit_price,
            'discount_type'   => $this->discount_type,
            'discount_value'  => $this->discount_value,
            'discount_amount' => $this->discount_amount,
            'amount'          => $this->amount,
            'tax_rate_id'     => $this->tax_rate_id,
            'tax'             => $this->tax_name !== null
                ? ['name' => $this->tax_name, 'code' => $this->tax_code, 'rate' => $this->tax_rate, 'treatment' => $this->tax_treatment]
                : null,
        ];
    }
}
