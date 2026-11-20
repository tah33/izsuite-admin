<?php

namespace App\Http\Resources\Pos;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** A POS cash movement as a session's page lists it (Doc 18 Phase F1): its number, kind, way, amount, the other account and why - and whether it waits, or was declined and why. */
class PosCashMovementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'number'         => $this->number,
            'type'           => $this->type,
            'direction'      => $this->direction,
            'amount'         => (string) $this->amount,
            'currency'       => $this->currency,
            'account'        => $this->account?->name,
            // Phase F2: a paid out's payee, and its expense's number once it moved.
            'payee'          => $this->payee_name,
            'expense'        => $this->expense?->number,
            'reason'         => $this->reason,
            'status'         => $this->status,
            'recorded_by'    => $this->creator?->name,
            'recorded_at'    => $this->created_at?->toIso8601String(),
            'decline_reason' => $this->decline_reason,
        ];
    }
}
