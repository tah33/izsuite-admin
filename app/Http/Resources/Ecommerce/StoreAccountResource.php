<?php

namespace App\Http\Resources\Ecommerce;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A storefront customer's account on its own page (Doc 19 Phase E3): what
 * StoreCustomerResource gives, with its language, whether it takes
 * marketing by e-mail - null until its e-mail is verified - and the store's
 * languages to choose from.
 */
class StoreAccountResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            ...(new StoreCustomerResource($this->resource['customer']))->resolve($request),
            'language'  => $this->resource['customer']->language,
            'marketing' => $this->resource['marketing'],
            'languages' => $this->resource['languages'],
        ];
    }
}
