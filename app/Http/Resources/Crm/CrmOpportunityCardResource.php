<?php

namespace App\Http\Resources\Crm;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A card of the board (Doc 17 Phase D4): its name, contact, value and
 * probability, its expected closing day and whether that has passed, whether
 * it has been at its stage too long and for how many days, its owner - and,
 * won or lost, the day it closed.
 */
class CrmOpportunityCardResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                  => $this->id,
            'name'                => $this->name,
            'contact'             => $this->contact?->name,
            'expected_value'      => $this->expected_value,
            'currency'            => $this->currency,
            'probability'         => $this->probability,
            'expected_close_date' => $this->expected_close_date?->toDateString(),
            'overdue'             => (bool) $this->overdue,
            'stale'               => (bool) $this->stale,
            'days_at_stage'       => (int) $this->days_at_stage,
            'owner'               => $this->owner?->name,
            'closed_on'           => $this->closed_on?->toDateString(),
        ];
    }
}
