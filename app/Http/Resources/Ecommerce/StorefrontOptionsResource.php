<?php

namespace App\Http\Resources\Ecommerce;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * What a storefront's form starts from that only the business knows (Doc 19
 * Phase A1): its currencies, the base one first, and its own country - null
 * when its profile names none. Languages, countries and locations come from
 * their own lists.
 */
class StorefrontOptionsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'currencies' => array_values($this->resource['currencies']),
            'country'    => $this->resource['country'],
        ];
    }
}
