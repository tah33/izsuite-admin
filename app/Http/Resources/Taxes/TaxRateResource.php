<?php

namespace App\Http\Resources\Taxes;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TaxRateResource extends JsonResource
{
    /**
     * The rate goes out as the column holds it, a string with four decimals
     * ("15.0000"), never as a float. Dates are "2026-10-28". applies_to and
     * default_for list sides: ['sales', 'purchases'].
     */
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'name'           => $this->name,
            'code'           => $this->code,
            'rate'           => $this->rate,
            'treatment'      => $this->treatment->value,
            'applies_to'     => $this->appliesTo(),
            'recoverability' => $this->recoverability,
            'effective_from' => $this->effective_from,
            'effective_to'   => $this->effective_to,
            'description'    => $this->description,
            'is_active'      => $this->is_active,
            'default_for'    => $this->defaultFor(),
        ];
    }
}
