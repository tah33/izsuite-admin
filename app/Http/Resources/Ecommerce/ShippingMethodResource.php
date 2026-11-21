<?php

namespace App\Http\Resources\Ecommerce;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A zone's shipping method as Settings → eCommerce shows and edits it (Doc
 * 19 Phase D1): its kind, its price or bands (base currency), its tax, its
 * delivery days, the order values and weights it takes, the categories it
 * carries (none: every product), and whether it is on.
 */
class ShippingMethodResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            'shipping_zone_id' => $this->shipping_zone_id,
            'name'             => $this->name,
            'kind'             => $this->kind,
            'price'            => $this->price,
            'rates'            => $this->rates,
            'tax_rate'         => $this->taxRate ? ['id' => $this->taxRate->id, 'name' => $this->taxRate->name, 'rate' => (string) $this->taxRate->rate] : null,
            'days_min'         => $this->days_min,
            'days_max'         => $this->days_max,
            'min_order'        => $this->min_order,
            'max_order'        => $this->max_order,
            'min_weight'       => $this->min_weight,
            'max_weight'       => $this->max_weight,
            'category_ids'     => $this->category_ids,
            'is_active'        => $this->is_active,
            'position'         => $this->position,
        ];
    }
}
