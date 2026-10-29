<?php

namespace App\Http\Resources\Purchases;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Settings > Purchases: what new purchases start with, and whether recording one checks for duplicates. */
class PurchaseSettingsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'payment_terms'   => $this->payment_terms,
            'notes'           => $this->notes,
            'duplicate_check' => $this->duplicate_check,
        ];
    }
}
