<?php

namespace App\Http\Resources\Pos;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A POS session's page (Doc 18 Phase B1), as PosSessionService::show() gives
 * it: the session - its register and location, cashier, status, opening and
 * closing - what it has taken so far while it is not closed, its Z report
 * once it is, and whether the user closes it.
 */
class PosSessionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $session = $this->resource['session'];
        $closing = $session->closing;
        $person  = fn ($user) => $user ? ['id' => $user->id, 'name' => $user->name] : null;

        return [
            'id'            => $session->id,
            'number'        => $session->number,
            'register'      => [
                'id'       => $session->register->id,
                'name'     => $session->register->name,
                'code'     => $session->register->code,
                'location' => ['id' => $session->register->location->id, 'name' => $session->register->location->name],
            ],
            'cashier'       => $person($session->cashier),
            'currency'      => $session->currency,
            'status'        => $session->status,
            'opened_at'     => $session->opened_at?->toIso8601String(),
            'opening_float' => (string) $session->opening_float,
            'opening_notes' => $session->opening_notes,
            'closed_at'     => $session->closed_at?->toIso8601String(),
            'closed_by'     => $person($session->closer),
            'totals'        => $this->resource['totals'],
            'z_report'      => $closing ? [
                'opening_float'   => (string) $closing->opening_float,
                'expected_cash'   => (string) $closing->expected_cash,
                'counted_cash'    => (string) $closing->counted_cash,
                'variance'        => (string) $closing->variance,
                'variance_reason' => $closing->variance_reason,
                'notes'           => $closing->notes,
                'totals'          => $closing->totals,
                'closed_by'       => $person($closing->closer),
                'closed_at'       => $closing->closed_at?->toIso8601String(),
            ] : null,
            'closes'        => $this->resource['closes'],
        ];
    }
}
