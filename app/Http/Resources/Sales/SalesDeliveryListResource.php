<?php

namespace App\Http\Resources\Sales;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** A row of the Deliveries page (Doc 16 Phase C). Expects order.contact and location. */
class SalesDeliveryListResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id,
            'number'          => $this->number,
            'status'          => $this->status,
            'delivery_date'   => $this->delivery_date,
            'delivered_on'    => $this->delivered_on,
            'order'           => ['id' => $this->order?->id, 'number' => $this->order?->number],
            'customer'        => $this->order?->customer_snapshot['name'] ?? $this->order?->contact?->name,
            'location'        => $this->location?->name,
            'carrier'         => $this->carrier,
            'tracking_number' => $this->tracking_number,
        ];
    }
}
