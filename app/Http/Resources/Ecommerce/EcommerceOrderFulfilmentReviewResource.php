<?php

namespace App\Http\Resources\Ecommerce;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * What fulfilling an online order would do now (Doc 19 Phase G1), for its
 * Ship Products and Ready for Pickup dialogs: whether it is shipped or
 * collected, where from - or where it is collected - the business's today,
 * and each product that ships with how many were ordered, are sent, and are
 * left. EcommerceOrderFulfilments::review() gives it.
 */
class EcommerceOrderFulfilmentReviewResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        ['order' => $order, 'lines' => $lines, 'today' => $today] = $this->resource;

        return [
            'delivery' => $order->delivery,
            'location' => $order->location?->name,
            'place'    => $order->delivery_snapshot['name'] ?? null,
            'today'    => $today,
            'lines'    => array_map(fn (array $row) => [
                'id'       => $row['line']->id,
                'title'    => $row['line']->title,
                'options'  => array_column($row['line']->options ?? [], 'value'),
                'sku'      => $row['line']->sku,
                'quantity' => (string) $row['line']->quantity,
                'sent'     => $row['sent'],
                'left'     => $row['left'],
            ], $lines),
        ];
    }
}
