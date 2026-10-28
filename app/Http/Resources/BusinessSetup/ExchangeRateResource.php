<?php

namespace App\Http\Resources\BusinessSetup;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One exchange rate: what one unit of `currency` is worth in the base currency
 * from `effective_date` on. The rate goes out as the column holds it, a string
 * with eight decimals ("121.50000000") - never as a float.
 */
class ExchangeRateResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'currency'       => $this->currency,
            'rate'           => $this->rate,
            'effective_date' => $this->effective_date,
        ];
    }
}
