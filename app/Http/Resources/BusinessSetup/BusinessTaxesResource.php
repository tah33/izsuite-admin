<?php

namespace App\Http\Resources\BusinessSetup;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Settings > Taxes: whether the business uses taxes. */
class BusinessTaxesResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'tax_mode' => $this->resource['tax_mode'],
        ];
    }
}
