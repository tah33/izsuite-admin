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
            'owner'               => $this->owner?->name,
        ];
    }
}
