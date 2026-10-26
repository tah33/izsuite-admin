<?php

namespace App\Http\Resources\BusinessSetup;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Settings > Currencies: the base currency and the others the business takes. */
class BusinessCurrenciesResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'base_currency'         => $this->resource['base_currency'],
            'additional_currencies' => $this->resource['additional_currencies'],
        ];
    }
}
