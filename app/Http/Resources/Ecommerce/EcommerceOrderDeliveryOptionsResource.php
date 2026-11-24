<?php

namespace App\Http\Resources\Ecommerce;

use App\Models\User\PickupPoint;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * What an online order could be delivered by, to an address being typed
 * (Doc 19 Phase F5a): whether it is delivered at all and the store sells
 * there; each shipping method that takes it, at its price now as the order
 * shows prices - none while quoted after the order - with its days; and the
 * pickup points. EcommerceOrderDeliveryEdit::quote() gives it.
 */
class EcommerceOrderDeliveryOptionsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $options = $this->resource;

        return [
            'needs_delivery' => $options['needs_delivery'],
            'sells_to'       => $options['sells_to'],
            'currency'       => $options['order']->currency,
            'methods'        => array_map(fn (array $offer) => [
                'id'       => $offer['method']->id,
                'name'     => $offer['method']->name,
                'price'    => $offer['shown'],
                'days_min' => $offer['method']->days_min,
                'days_max' => $offer['method']->days_max,
            ], $options['methods']),
            'points'         => $options['points']->map(fn (PickupPoint $point) => [
                'id'           => $point->id,
                'name'         => $point->location?->name,
                'ready_within' => $point->ready_within,
            ])->values(),
        ];
    }
}
