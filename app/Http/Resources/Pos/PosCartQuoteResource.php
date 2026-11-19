<?php

namespace App\Http\Resources\Pos;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A POS cart priced and totalled (Doc 18 Phase C1), in the register's
 * currency: each line in order - its item, its price, where the price comes
 * from and its amount - and the subtotal, each tax and the total.
 */
class PosCartQuoteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'lines'       => array_map(fn (array $line) => [
                'item_id'      => $line['item_id'],
                'price'        => $line['price'],
                'price_source' => $line['source'],
                'price_list'   => $line['price_list'],
                'amount'       => $line['amount'],
            ], $this->resource['lines']),
            'subtotal'    => $this->resource['subtotal'],
            'grand_total' => $this->resource['grand_total'],
            'taxes'       => array_map(fn (array $tax) => ['name' => $tax['name'], 'rate' => (string) $tax['rate'], 'amount' => $tax['tax_amount']], $this->resource['taxes']),
        ];
    }
}
