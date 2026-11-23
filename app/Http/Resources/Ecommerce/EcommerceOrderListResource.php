<?php

namespace App\Http\Resources\Ecommerce;

use App\Models\User\EcommerceOrder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * An online order as the Orders page lists it (Doc 19 Phase F1): its number
 * and when it was placed, its storefront, its customer as given, how many
 * products it holds, how it reaches them, its total and its three statuses.
 *
 * @property EcommerceOrder $resource
 */
class EcommerceOrderListResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $order = $this->resource;

        return [
            'id'                => $order->id,
            'number'            => $order->number,
            'placed_at'         => $order->placed_at?->toIso8601String(),
            'storefront'        => $order->storefront?->name,
            'customer'          => ['name' => $order->customer['name'] ?? null, 'email' => $order->customer['email'] ?? null],
            'products'          => (int) ($order->products ?? 0),
            'delivery'          => $order->delivery,
            'total'             => (string) $order->grand_total,
            'currency'          => $order->currency,
            'status'            => $order->status,
            'payment_status'    => $order->payment_status,
            'fulfilment_status' => $order->fulfilment_status,
        ];
    }
}
