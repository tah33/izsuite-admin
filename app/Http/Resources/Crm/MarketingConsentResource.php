<?php

namespace App\Http\Resources\Crm;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A lead's or contact's answer about marketing (Doc 17 Phase I3), as its
 * card shows it: the purpose and channel, how it stands - given, refused,
 * withdrawn, or expired once a consent given passed its day - and whether it
 * is the one standing for them, where it came from, when and by whom, its
 * expiry and evidence, and its withdrawal.
 */
class MarketingConsentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id,
            'purpose'         => $this->purpose ? ['id' => $this->purpose->id, 'name' => $this->purpose->name] : null,
            'channel'         => $this->channel,
            'status'          => $this->shown_status ?? $this->status,
            'standing'        => (bool) $this->standing,
            'source'          => $this->source,
            'recorded_at'     => $this->recorded_at?->toIso8601String(),
            'recorded_by'     => $this->recorder?->name,
            'expires_on'      => $this->expires_on?->toDateString(),
            'evidence'        => $this->evidence,
            'withdrawn_at'    => $this->withdrawn_at?->toIso8601String(),
            'withdrawn_by'    => $this->withdrawer?->name,
            'withdrawal_note' => $this->withdrawal_note,
        ];
    }
}
