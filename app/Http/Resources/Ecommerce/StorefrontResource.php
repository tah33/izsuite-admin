<?php

namespace App\Http\Resources\Ecommerce;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A storefront (Doc 19 Phase A1) as the Storefronts page shows and edits it:
 * its code, status, languages, currencies and countries with their defaults,
 * the location it sells from - and, from Phase D3, the price list it sells
 * at - and its support e-mail and phone.
 */
class StorefrontResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            'name'             => $this->name,
            'code'             => $this->code,
            'status'           => $this->status,
            'location'         => $this->location ? ['id' => $this->location->id, 'name' => $this->location->name] : null,
            // Phase D3: with full Sales, the price list it sells at.
            'price_list'       => $this->priceList ? ['id' => $this->priceList->id, 'name' => $this->priceList->name] : null,
            'default_language' => $this->default_language,
            'languages'        => $this->languages,
            'currency'         => $this->currency,
            'currencies'       => $this->currencies,
            'countries'        => $this->countries,
            'support_email'    => $this->support_email,
            'support_phone'    => $this->support_phone,
        ];
    }
}
