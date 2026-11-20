<?php

namespace App\Http\Resources\Pos;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A held POS order as resuming it loads it into the cart (Doc 18 Phase D1):
 * its customer - null for the walk-in one - its lines, each with its note,
 * price typed over and why, and discount; the order's discount and note;
 * what changed since it was held, in words; and its total then and now.
 */
class PosHeldCartResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        ['held' => $held, 'contact' => $contact, 'lines' => $lines] = $this->resource;

        return [
            'id'         => $held->id,
            'number'     => $held->number,
            'customer'   => $contact ? ['id' => $contact->id, 'name' => $contact->name] : null,
            'lines'      => array_map(fn (array $line) => [
                'item_id'         => $line['item']->id,
                'name'            => $line['item']->name,
                'unit'            => $line['item']->unit,
                'quantity'        => $line['quantity'],
                'note'            => $line['note'],
                'price'           => $line['price'],
                'override_reason' => $line['override_reason'],
                'discount_type'   => $line['discount_type'],
                'discount_value'  => $line['discount_value'],
            ], $lines),
            'discount'   => $this->resource['discount'],
            'note'       => $this->resource['note'],
            'changes'    => $this->resource['changes'],
            'held_total' => (string) $held->grand_total,
            'total'      => $this->resource['total'],
        ];
    }
}
