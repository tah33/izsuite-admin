<?php

namespace App\Http\Resources\Crm;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A row of the Opportunities page (Doc 17 Phase D1): its name, contact,
 * stage and probability, what it may be worth and weighs - in its currency
 * and the base one - when it may close, whether that day has passed while
 * it is open, and its owner.
 */
class CrmOpportunityListResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                  => $this->id,
            'name'                => $this->name,
            'contact'             => $this->contact?->name,
            'stage'               => $this->stage?->name,
            'status'              => $this->status,
            'probability'         => $this->probability,
            'expected_value'      => $this->expected_value,
            'currency'            => $this->currency,
            'weighted_value'      => $this->weighted_value,
            'base_weighted_value' => $this->base_weighted_value,
            'expected_close_date' => $this->expected_close_date?->toDateString(),
            'overdue'             => (bool) $this->overdue,
            // Phase D3a: at its stage for longer than its stale-after period, and for how many days.
            'stale'               => (bool) $this->stale,
            'days_at_stage'       => (int) $this->days_at_stage,
            'owner'               => $this->owner?->name,
            // Archived (Phase D2a): listed only under the Archived filter.
            'archived'            => $this->archived_at !== null,
        ];
    }
}
