<?php

namespace App\Http\Resources\Pos;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** What a POS return would credit (Doc 18 Phase E1): its total, at the sale's prices, discounts and taxes, and its taxes. */
class PosReturnQuoteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'total'     => (string) $this->resource['total'],
            'tax_total' => (string) $this->resource['tax_total'],
        ];
    }
}
