<?php

namespace App\Http\Resources\Crm;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** An entry of a CRM list (Doc 17 Phase A1): a starting one is izSuite's, switched off rather than deleted. */
class CrmOptionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'        => $this->id,
            'name'      => $this->name,
            'is_active' => (bool) $this->is_active,
            'starter'   => $this->isStarter(),
            // An activity type's (Doc 17 Phase E1).
            'needs_outcome' => $this->when($this->kind === 'activity_type', fn () => (bool) $this->needs_outcome),
        ];
    }
}
