<?php

namespace App\Http\Resources\Ecommerce;

use App\Models\User\CatalogProduct;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A listing a discount may be for (Doc 19 Phase H1), as its form offers it:
 * its id and title.
 *
 * @property CatalogProduct $resource
 */
class EcommerceDiscountProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => $this->resource->id, 'title' => $this->resource->title];
    }
}
