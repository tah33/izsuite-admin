<?php

namespace App\Http\Resources\Sales;

use App\Http\Resources\Concerns\HidesValues;
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
    use HidesValues;

    /** What a user without Sales Values does not see (Doc 12 section 14). */
    public const VALUES = ['unit_price', 'discount_value', 'discount_amount', 'amount', 'tax_amount'];

    public function toArray(Request $request): array
    {
        return $this->hideValues('sales', [
            'id'              => $this->id,
            'source_line_id'  => $this->source_line_id,
            // The deposit invoice it deducts (Doc 16 Phase D2).
            'deposit_invoice_id' => $this->deposit_invoice_id,
            // The price list and rule that priced it, kept as they were (Doc 16 Phase F1).
            'price_list_id'      => $this->price_list_id,
            'price_list_rule_id' => $this->price_list_rule_id,
            'price_list'         => $this->price_list_id ? $this->priceList?->name : null,
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
        ], self::VALUES);
    }
}
