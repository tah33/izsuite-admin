<?php

namespace App\Http\Resources\Crm;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The CRM Overview's figures (Doc 17 section 17.4 - Phase G1), as
 * CrmOverviewService gives them: the period and currency they are in, the
 * closing-soon period, the leads' figures (null without them), the open,
 * closing-soon, won and lost opportunities - each value with how many were
 * left out for want of a rate - and the overdue activities. Phase G3b adds
 * the follow-up periods, the leads' warnings (in the queue, not contacted in
 * time) and the opportunities' (no activity planned, stale, quiet).
 */
class CrmOverviewResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $figures = $this->resource;
        $money   = fn (array $value) => ['amount' => $value['total'], 'unrated' => $value['unrated']];

        return [
            'from'              => $figures['from'],
            'to'                => $figures['to'],
            'currency'          => $figures['currency'],
            'closing_soon_days' => $figures['closing_soon_days'],
            'lead_response_days'   => $figures['lead_response_days'],
            'recent_activity_days' => $figures['recent_activity_days'],
            'leads'             => $figures['leads'],
            'open'              => ['count' => $figures['open']['count'], 'value' => $money($figures['open']['value']), 'weighted' => $money($figures['open']['weighted'])],
            'closing_soon'      => ['count' => $figures['closing_soon']['count'], 'value' => $money($figures['closing_soon']['value'])],
            'won'               => ['count' => $figures['won']['count'], 'value' => $money($figures['won']['value'])],
            'lost'              => ['count' => $figures['lost']['count'], 'value' => $money($figures['lost']['value'])],
            'overdue_activities' => $figures['overdue_activities'],
            'follow_up'         => $figures['follow_up'],
        ];
    }
}
