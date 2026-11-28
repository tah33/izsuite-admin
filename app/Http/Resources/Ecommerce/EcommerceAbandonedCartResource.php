<?php

namespace App\Http\Resources\Ecommerce;

use App\Models\User\EcommerceCart;
use App\Models\User\EcommerceCartLine;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A cart as the Abandoned Carts page shows it (Doc 19 Phase H3a): its
 * customer - their name and e-mail, and whether it was an account's - its
 * storefront, its first two products and how many it holds, its value as
 * last priced, its last activity, whether its customer agreed to marketing by
 * e-mail, its status and, once converted, its order.
 *
 * @property EcommerceCart $resource
 */
class EcommerceAbandonedCartResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $cart = $this->resource;

        return [
            'id'               => $cart->id,
            'storefront'       => $cart->storefront?->name,
            'customer'         => ['name' => $cart->customer_name, 'email' => $cart->customer_email, 'account' => $cart->storefront_customer_id !== null],
            'items'            => $cart->lines->take(2)->map(fn (EcommerceCartLine $line) => ['title' => $line->product?->title, 'quantity' => rtrim(rtrim((string) $line->quantity, '0'), '.')])->values(),
            'lines'            => $cart->lines->count(),
            'value'            => (string) $cart->grand_total,
            'currency'         => $cart->currency,
            'last_activity_at' => $cart->last_activity_at?->toIso8601String(),
            'marketing'        => (bool) $cart->marketing,
            'status'           => $cart->status,
            'order'            => $cart->order ? ['id' => $cart->order->id, 'number' => $cart->order->number] : null,
        ];
    }
}
