<?php

namespace App\Http\Resources\Ecommerce;

use App\Support\AddressFormat;
use App\Support\Countries;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A location of the business as a pickup point (Doc 19 Phase D1): its name
 * and address on one line, whether it is active, and whether its customers
 * may collect there - with its instructions and when an order is ready.
 */
class PickupLocationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $point = $this->pickupPoint;

        return [
            'location_id'  => $this->id,
            'name'         => $this->name,
            'address'      => implode(', ', AddressFormat::lines(Countries::addressFormat($this->country), [
                'line1' => $this->address_line_1, 'line2' => $this->address_line_2, 'city' => $this->city, 'region' => $this->state,
                'postal' => $this->postal_code, 'country' => $this->country ? Countries::name($this->country) ?? $this->country : null,
            ])) ?: null,
            'is_active'    => $this->is_active,
            'offered'      => (bool) $point?->is_active,
            'instructions' => $point?->instructions,
            'ready_within' => $point?->ready_within,
        ];
    }
}
