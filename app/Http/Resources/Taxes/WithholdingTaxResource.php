<?php

namespace App\Http\Resources\Taxes;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WithholdingTaxResource extends JsonResource
{
    /**
     * The rate goes out as the column holds it, a string with four decimals
     * ("5.0000"), never as a float. Dates are "2026-10-28". applies_to lists
     * the payments it is withheld from: ['customer_payments'].
     */
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'name'           => $this->name,
            'code'           => $this->code,
            'rate'           => $this->rate,
            'applies_to'     => $this->appliesTo(),
            'effective_from' => $this->effective_from,
            'effective_to'   => $this->effective_to,
            'is_active'      => $this->is_active,
        ];
    }
}
