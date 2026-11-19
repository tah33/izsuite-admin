<?php

namespace App\Http\Resources\Pos;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A POS session as the Sessions page lists it (Doc 18 Phase B1): its number,
 * register, cashier, status, when it opened and closed, its opening float
 * and - once closed - the cash expected and counted and the variance.
 */
class PosSessionListResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $closing = $this->closing;

        return [
            'id'            => $this->id,
            'number'        => $this->number,
            'register'      => ['id' => $this->register->id, 'name' => $this->register->name, 'code' => $this->register->code],
            'cashier'       => $this->cashier ? ['id' => $this->cashier->id, 'name' => $this->cashier->name] : null,
            'currency'      => $this->currency,
            'status'        => $this->status,
            'opened_at'     => $this->opened_at?->toIso8601String(),
            'closed_at'     => $this->closed_at?->toIso8601String(),
            'opening_float' => (string) $this->opening_float,
            'closing'       => $closing ? [
                'expected_cash' => (string) $closing->expected_cash,
                'counted_cash'  => (string) $closing->counted_cash,
                'variance'      => (string) $closing->variance,
            ] : null,
        ];
    }
}
