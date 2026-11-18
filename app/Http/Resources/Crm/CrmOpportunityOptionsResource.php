<?php

namespace App\Http\Resources\Crm;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * What an opportunity's form and the Opportunities page's filters choose from
 * (Doc 17 Phase D1): since Phase D3b the pipelines switched on - whom each is
 * for, with its open stages switched on and their probabilities - and every
 * stage of theirs switched on with its pipeline (to filter by), the lead
 * sources and sales teams
 * switched on, the people it can be given to with the locations each may use
 * (null for every one), and where a new one starts and who owns it.
 */
class CrmOpportunityOptionsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $named = fn ($record) => ['id' => $record->id, 'name' => $record->name];

        return [
            'all_stages'   => $this->resource['all_stages']->map(fn ($stage) => [...$named($stage), 'pipeline_id' => $stage->crm_pipeline_id])->values(),
            'pipelines'    => $this->resource['pipelines'],
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
