<?php

namespace App\Http\Resources\Sales;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** A row of the Price Lists page (Doc 16 Phase F1). Expects rules_count. */
class PriceListListResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'            => $this->id,
            'name'          => $this->name,
            'currency'      => $this->currency,
            'is_active'     => $this->is_active,
            'valid_from'    => $this->valid_from,
            'valid_to'      => $this->valid_to,
            'all_customers' => $this->all_customers,
            'priority'      => $this->priority,
            'rules'         => (int) $this->rules_count,
        ];
    }
}