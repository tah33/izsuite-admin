<?php

namespace App\Http\Resources\Ecommerce;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * What a storefront's products offer to filter by (Doc 19 Phase C3): the
 * lowest and highest price shown - null with none - and each option's and
 * attribute's values, by name (StorefrontFacets).
 */
class StoreFacetsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'price'      => $this->resource['price'],
            'options'    => $this->resource['options'],
            'attributes' => $this->resource['attributes'],
        ];
    }
}
