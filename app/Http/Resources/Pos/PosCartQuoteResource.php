<?php

namespace App\Http\Resources\Pos;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A POS cart priced and totalled (Doc 18 Phases C1 and C3), in the
 * register's currency: each line in order - its item, its price, the price
 * an override replaced, where its price came from, its discount and amount -
 * and the subtotal, the order's discount, each tax and the total; and
 * whether its discounts go beyond Settings' limit, with the limit.
 */
class PosCartQuoteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'lines'          => array_map(fn (array $line) => [
                'item_id'      => $line['item_id'],
                'price'        => $line['price'],
                'standard'     => $line['source'] === 'override' ? $line['standard'] : null,
                'price_source' => $line['source'],
                'price_list'   => $line['price_list'],
                'discount'     => $line['discount'],
                'amount'       => $line['amount'],
            ], $this->resource['lines']),
            'subtotal'       => $this->resource['subtotal'],
            'discount_total' => $this->resource['discount_total'],
            'grand_total'    => $this->resource['grand_total'],
            'taxes'          => array_map(fn (array $tax) => ['name' => $tax['name'], 'rate' => (string) $tax['rate'], 'amount' => $tax['tax_amount']], $this->resource['taxes']),
            'beyond_limit'   => $this->resource['beyond_limit'],
            'limit'          => $this->resource['limit'],
        ];
    }
}
