<?php

namespace App\Http\Resources\Ecommerce;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Settings → eCommerce's shipping and pickup (Doc 19 Phase D1): the zones in
 * their order with their methods, the locations as pickup points, and what a
 * method chooses from - the sales tax rates (null while taxes are off) and
 * the item categories - with the base currency its prices are in.
 */
class EcommerceShippingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'zones'      => ShippingZoneResource::collection($this->resource['zones'])->resolve($request),
            'pickup'     => PickupLocationResource::collection($this->resource['pickup'])->resolve($request),
            'tax_rates'  => $this->resource['tax_rates']?->map(fn ($rate) => ['id' => $rate->id, 'name' => $rate->name, 'rate' => (string) $rate->rate])->values(),
            'categories' => $this->resource['categories']->map(fn ($category) => ['id' => $category->id, 'name' => $category->name])->values(),
            'currency'   => $this->resource['currency'],
        ];
    }
}
