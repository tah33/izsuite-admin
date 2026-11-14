<?php

namespace App\Http\Resources\Sales;

use App\Models\User\PriceListRule;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** A price list's form (Doc 16 Phase F1): all of it, its rules with what they name. */
class PriceListResource extends JsonResource
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
            'customers'     => $this->contacts->map(fn ($contact) => ['id' => $contact->id, 'name' => $contact->name])->values()->all(),
            'all_locations' => $this->all_locations,
            'location_ids'  => $this->locations->pluck('id')->all(),
            'priority'      => $this->priority,
            'notes'         => $this->notes,
            'rules'         => $this->rules->map(fn (PriceListRule $rule) => [
                'target'           => $rule->target,
                'item_id'          => $rule->item_id,
                'item_category_id' => $rule->item_category_id,
                'item_brand_id'    => $rule->item_brand_id,
                'names'            => $rule->item?->name ?? $rule->category?->name ?? $rule->brand?->name,
                'min_quantity'     => $rule->min_quantity,
                'method'           => $rule->method,
                'value'            => $rule->value,
                'valid_from'       => $rule->valid_from,
                'valid_to'         => $rule->valid_to,
            ])->all(),
        ];
    }
}