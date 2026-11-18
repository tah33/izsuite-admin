<?php

namespace App\Http\Resources\Crm;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A lead assignment rule as Settings → CRM shows and edits it (Doc 17 Phase
 * I1): whether it is on, what a lead must have - each named, null for any -
 * and whom it gives leads to, with a team's method.
 */
class CrmAssignmentRuleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $named = fn ($record) => $record ? ['id' => $record->id, 'name' => $record->name] : null;

        return [
            'id'         => $this->id,
            'name'       => $this->name,
            'is_active'  => $this->is_active,
            'source'     => $named($this->source),
            'location'   => $named($this->location),
            'sales_team' => $named($this->salesTeam),
            'country'    => $this->country,
            'item'       => $named($this->item),
            'user'       => $named($this->user),
            'team'       => $named($this->team),
            'method'     => $this->method,
        ];
    }
}
