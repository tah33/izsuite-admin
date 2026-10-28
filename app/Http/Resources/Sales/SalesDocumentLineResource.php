<?php

namespace App\Http\Resources\Sales;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One line of a sales document, as kept: its own name, price and tax - never
 * read again from the item or the rate. Quantities go out as strings to three
 * decimals, prices and amounts to the cent, the tax rate to four. A credit
 * note's line names the invoice line it credits (source_line_id).
 */
class SalesDocumentLineResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id,
            'source_line_id'  => $this->source_line_id,
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
            'tax_amount'      => $this->tax_amount,
            'components'      => $this->components,
            'warranty'        => $this->warranty,
        ];
    }
}
