<?php

namespace App\Http\Resources\Pos;

use App\Models\User\PosOrderPayment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A sale as a POS return starts from it (Doc 18 Phase E1): its receipt
 * number, when, its customer - and whether that is the walk-in one - each
 * item with how much was sold, how much is still returnable and its price,
 * and how it was paid.
 */
class PosReturnSaleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        ['order' => $order, 'lines' => $lines] = $this->resource;

        return [
            'id'        => $order->id,
            'number'    => $order->number,
            'sold_at'   => $order->completed_at?->toIso8601String(),
            'currency'  => $order->currency,
            'customer'  => $order->contact?->name,
            'walk_in'   => $this->resource['walk_in'],
            'lines'     => array_map(fn (array $left) => [
                'line_id'    => $left['line']->id,
                'name'       => $left['line']->name,
                'unit'       => $left['line']->unit,
                'sold'       => (string) $left['line']->quantity,
                'returnable' => $left['remaining_quantity'],
                'unit_price' => (string) $left['line']->unit_price,
            ], $lines),
            'paid_by'   => $order->payments->where('status', PosOrderPayment::COMPLETED)->pluck('method_name')->unique()->values()->all(),
        ];
    }
}
