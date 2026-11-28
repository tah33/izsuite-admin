<?php

namespace App\Http\Resources\Ecommerce;

use App\Models\User\ItemCategory;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * An item category a discount may be for (Doc 19 Phase H2b), as its form
 * offers it: its id and name.
 *
 * @property ItemCategory $resource
 */
class EcommerceDiscountCategoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => $this->resource->id, 'name' => $this->resource->name];
    }
}
