<?php

namespace App\Http\Resources\Ecommerce;

use App\Models\User\PriceList;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * What a storefront's form starts from that only the business knows (Doc 19
 * Phase A1): its currencies, the base one first, and its own country - null
 * when its profile names none. Languages, countries and locations come from
 * their own lists. Phase D3: with full Sales, its active price lists - each
 * with its currency and the locations it is for (none: every one) - and null
 * without.
 */
class StorefrontOptionsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'currencies'  => array_values($this->resource['currencies']),
            'country'     => $this->resource['country'],
            'price_lists' => $this->resource['price_lists']?->map(fn (PriceList $list) => [
                'id'           => $list->id,
                'name'         => $list->name,
                'currency'     => $list->currency,
                'location_ids' => $list->all_locations ? null : $list->locations->pluck('id')->values(),
            ])->values(),
        ];
    }
}
