<?php

namespace App\Http\Resources\Crm;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * What an opportunity's form and the Opportunities page's filters choose from
 * (Doc 17 Phase D1): the open stages switched on with their probabilities,
 * every stage switched on (to filter by), the lead sources and sales teams
 * switched on, the people it can be given to with the locations each may use
 * (null for every one), and where a new one starts and who owns it.
 */
class CrmOpportunityOptionsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $named = fn ($record) => ['id' => $record->id, 'name' => $record->name];

        return [
            'stages'       => $this->resource['stages']->map(fn ($stage) => [...$named($stage), 'probability' => $stage->probability])->values(),
            'all_stages'   => $this->resource['all_stages']->map($named)->values(),
            'sources'      => $this->resource['sources']->map($named)->values(),
            // Phase D2a: what an opportunity is lost for.
            'lost_reasons' => $this->resource['lost_reasons']->map($named)->values(),
            'teams'        => $this->resource['teams']->map($named)->values(),
            'owners'       => $this->resource['owners'],
            'location_id'  => $this->resource['location_id'],
            'owner_id'     => $this->resource['owner_id'],
        ];
    }
}
