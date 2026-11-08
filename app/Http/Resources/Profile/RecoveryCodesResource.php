<?php

namespace App\Http\Resources\Profile;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A new set of recovery codes (Doc 12 section 22), shown this once: only
 * their hashes are kept.
 *
 * @property list<string> $resource
 */
class RecoveryCodesResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'recovery_codes' => $this->resource,
        ];
    }
}
