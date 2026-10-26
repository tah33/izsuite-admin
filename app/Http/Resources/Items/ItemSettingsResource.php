<?php

namespace App\Http\Resources\Items;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Settings > Items: whether the business uses Brand Management. */
class ItemSettingsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'brands_enabled' => $this->resource['brands_enabled'],
        ];
    }
}
