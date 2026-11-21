<?php

namespace App\Http\Resources\Ecommerce;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A shipping zone as Settings → eCommerce shows and edits it (Doc 19 Phase
 * D1): its countries, the regions, cities and postal codes it is narrowed to
 * (each empty for every one), its place, and its methods in their order.
 */
class ShippingZoneResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->id,
            'name'         => $this->name,
            'countries'    => $this->countries,
            'regions'      => $this->regions ?? [],
            'cities'       => $this->cities ?? [],
            'postal_codes' => $this->postal_codes ?? [],
            'position'     => $this->position,
            'methods'      => ShippingMethodResource::collection($this->methods)->resolve($request),
        ];
    }
}
