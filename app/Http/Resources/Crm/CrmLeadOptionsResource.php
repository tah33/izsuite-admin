<?php

namespace App\Http\Resources\Crm;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * What a lead's form and the Leads page's filters choose from (Doc 17 Phase
 * B1): the lead sources and sales teams switched on, the people a lead can
 * be given to with the locations each may use (null for every one), and
 * where a new lead starts and who owns it unless someone else is chosen -
 * (Phase B2a) what qualifying a lead needs filled in, and (Phase G3b) the
 * days the "uncontacted" filter is named with.
 */
class CrmLeadOptionsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $named = fn ($record) => ['id' => $record->id, 'name' => $record->name];

        return [
            'sources'     => $this->resource['sources']->map($named)->values(),
            'teams'       => $this->resource['teams']->map($named)->values(),
            'owners'      => $this->resource['owners'],
            'location_id'            => $this->resource['location_id'],
            'owner_id'               => $this->resource['owner_id'],
            'qualification_required' => $this->resource['qualification_required'],
            'lead_response_days'     => $this->resource['lead_response_days'],
        ];
    }
}
