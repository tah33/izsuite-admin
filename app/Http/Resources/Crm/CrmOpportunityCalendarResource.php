<?php

namespace App\Http\Resources\Crm;

use App\Models\User\CrmOpportunity;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A month of the calendar (Doc 17 Phase D4): the month, the business's today
 * and the day its week starts (0 Sunday to 6 Saturday), how many
 * opportunities the filters find in it, and those it shows, each on its
 * expected closing day: its name, contact, stage, status, value and whether
 * that day has passed while it is open.
 */
class CrmOpportunityCalendarResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'month'      => $this->resource['month'],
            'today'      => $this->resource['today'],
            'week_start' => $this->resource['week_start'],
            'count'      => $this->resource['count'],
            'entries'    => $this->resource['entries']->map(fn (CrmOpportunity $opportunity) => [
                'id'                  => $opportunity->id,
                'name'                => $opportunity->name,
                'contact'             => $opportunity->contact?->name,
                'stage'               => $opportunity->stage?->name,
                'status'              => $opportunity->status,
                'expected_value'      => $opportunity->expected_value,
                'currency'            => $opportunity->currency,
                'expected_close_date' => $opportunity->expected_close_date?->toDateString(),
                'overdue'             => (bool) $opportunity->overdue,
            ])->values(),
        ];
    }
}
