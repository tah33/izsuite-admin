<?php

namespace App\Http\Resources\Ecommerce;

use App\Support\Countries;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** An address a storefront customer keeps (Doc 19 Phase E2): its fields as checkout takes them, its country's name, and whether it is a default. */
class StoreAddressResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            'line1'            => $this->line1,
            'line2'            => $this->line2,
            'city'             => $this->city,
            'region'           => $this->region,
            'postal_code'      => $this->postal_code,
            'country'          => $this->country,
            'country_name'     => Countries::name($this->country) ?? $this->country,
            'default_billing'  => (bool) $this->is_default_billing,
            'default_shipping' => (bool) $this->is_default_shipping,
        ];
    }
}
