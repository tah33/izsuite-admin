<?php

namespace App\Http\Resources\Crm;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** A row of the Leads page (Doc 17 Phase B1). */
class CrmLeadListResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id,
            'name'            => $this->name,
            'business_name'   => $this->business_name,
            'status'          => $this->status,
            'priority'        => $this->priority,
            'source'          => $this->source?->name,
            'owner'           => $this->owner?->name,
            'estimated_value' => $this->estimated_value,
            'currency'        => $this->currency,
            'added_on'        => $this->created_at?->toDateString(),
            // Phase C2: open, with a possible duplicate not decided about.
            'possible_duplicate' => (bool) $this->possible_duplicate,
        ];
    }
}
