<?php

namespace App\Http\Resources\Ecommerce;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A storefront's own domain (Doc 19 Phase A3): whether it is verified and
 * the primary one, when its DNS was last looked at, and the TXT record that
 * proves it is the business's.
 */
class StorefrontDomainResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'host'        => $this->host,
            'verified'    => $this->isVerified(),
            'verified_at' => $this->verified_at?->toIso8601String(),
            'checked_at'  => $this->checked_at?->toIso8601String(),
            'is_primary'  => (bool) $this->is_primary,
            'record'      => ['type' => 'TXT', 'name' => $this->recordName(), 'value' => $this->recordValue()],
        ];
    }
}
