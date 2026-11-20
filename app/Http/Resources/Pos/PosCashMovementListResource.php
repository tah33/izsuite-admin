<?php

namespace App\Http\Resources\Pos;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A POS cash movement as the Cash Movements page lists it (Doc 18 Phase
 * G1): as a session's page shows it, with its register and session.
 */
class PosCashMovementListResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            ...(new PosCashMovementResource($this->resource))->toArray($request),
            'register' => $this->register ? "{$this->register->name} ({$this->register->code})" : null,
            'session'  => $this->session ? ['id' => $this->session->id, 'number' => $this->session->number] : null,
        ];
    }
}
