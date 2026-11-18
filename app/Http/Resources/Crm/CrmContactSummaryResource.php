<?php

namespace App\Http\Resources\Crm;

use App\Models\User\CrmOpportunity;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The CRM part of a contact's profile (Doc 17 Phase F4): where they came
 * from, their open, won and lost opportunities - counts, and values in the
 * base currency with how many had no rate today - and the opportunities
 * themselves, each with its stage and probability, value, and its closing
 * day (expected while open, the day it ended once won or lost).
 */
class CrmContactSummaryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $open = $this->resource['open'];
        $won  = $this->resource['won'];

        return [
            'source'        => $this->resource['source'],
            'currency'      => $this->resource['currency'],
            'open'          => ['count' => $open['count'], 'value' => $open['whole']['total'], 'weighted' => $open['weighted'], 'unrated' => $open['whole']['unrated']],
            'won'           => ['count' => $won['count'], 'value' => $won['whole']['total'], 'unrated' => $won['whole']['unrated']],
            'lost'          => $this->resource['lost'],
            'opportunities' => $this->resource['opportunities']->map(fn (CrmOpportunity $opportunity) => [
                'id'          => $opportunity->id,
                'name'        => $opportunity->name,
                'status'      => $opportunity->status,
                'stage'       => $opportunity->stage?->name,
                'probability' => $opportunity->probability,
                'value'       => (string) $opportunity->expected_value,
                'currency'    => $opportunity->currency,
                'date'        => (string) ($opportunity->status === CrmOpportunity::OPEN ? $opportunity->getRawOriginal('expected_close_date') : $opportunity->getRawOriginal('closed_on')) ?: null,
            ])->all(),
        ];
    }
}
