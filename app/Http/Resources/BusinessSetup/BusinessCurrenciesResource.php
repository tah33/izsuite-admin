<?php

namespace App\Http\Resources\BusinessSetup;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Settings > Currencies: the base currency, the others the business takes, and how many decimals its exchange rates keep. */
class BusinessCurrenciesResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'base_currency'         => $this->resource['base_currency'],
            'additional_currencies' => $this->resource['additional_currencies'],
            'rate_precision'        => $this->resource['rate_precision'],
        ];
    }
}
