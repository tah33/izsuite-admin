<?php

namespace App\Http\Resources\Ecommerce;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * An order in a storefront customer's list of theirs (Doc 19 Phase E2): its
 * number, when it was placed, its three statuses, its total, and how many
 * products it holds. Its own page comes by its id.
 */
class StoreAccountOrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                => $this->id,
            'number'            => $this->number,
            'placed_at'         => $this->placed_at?->toIso8601String(),
            'status'            => $this->status,
            'payment_status'    => $this->payment_status,
            'fulfilment_status' => $this->fulfilment_status,
            'currency'          => $this->currency,
            'grand_total'       => $this->grand_total,
            'products'          => (int) ($this->products ?? 0),
        ];
    }
}
