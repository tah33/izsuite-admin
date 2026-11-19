<?php

namespace App\Http\Resources\Crm;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A lead's or contact's marketing consent (Doc 17 Phase I3), as
 * CrmConsentService::index() gives it: its answers, the latest first, and
 * the consent purposes switched on that an answer is recorded for.
 */
class MarketingConsentsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'consents' => MarketingConsentResource::collection($this->resource['consents']),
            'purposes' => $this->resource['purposes']->map(fn ($purpose) => ['id' => $purpose->id, 'name' => $purpose->name])->values(),
        ];
    }
}
