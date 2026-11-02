<?php

namespace App\Http\Resources\BusinessSetup;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A payment term, as Settings > Payment Terms lists it and the term pickers
 * offer it: its name and days, whether it is offered to new transactions,
 * and `in_use` - a contact, default or document holds its days, so it is
 * switched off rather than deleted. Expects in_use set (PaymentTermService).
 */
class PaymentTermResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'        => $this->id,
            'name'      => $this->name,
            'days'      => $this->days,
            'is_active' => $this->is_active,
            'in_use'    => (bool) ($this->in_use ?? false),
        ];
    }
}
