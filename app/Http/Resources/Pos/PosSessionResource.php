<?php

namespace App\Http\Resources\Pos;

use App\Models\User\PosSessionClosing;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A POS session's page (Doc 18 Phases B1 and B2a), as
 * PosSessionService::show() gives it: the session - its register and
 * location, cashier, status, opening and closing - what it has taken so far
 * while it is open, its Z report once counted (with why a supervisor closed
 * it, and whether its variance waits for approval or was approved), its
 * earlier closings that were reopened, whether it is the user's own, what
 * the user may do with it, and (Phase B2b) the notes and coins it is counted
 * by while it is open.
 */
class PosSessionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $session = $this->resource['session'];
        $latest  = $session->closings->last();
        $person  = fn ($user) => $user ? ['id' => $user->id, 'name' => $user->name] : null;
        $when    = fn ($moment) => $moment?->toIso8601String();
        $counted = fn (PosSessionClosing $closing) => [
            'expected_cash' => (string) $closing->expected_cash,
            'counted_cash'  => (string) $closing->counted_cash,
            'variance'      => (string) $closing->variance,
            'closed_by'     => $person($closing->closer),
            'closed_at'     => $when($closing->closed_at),
        ];

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
            'opened_at'     => $when($session->opened_at),
            'opening_float' => (string) $session->opening_float,
            'opening_notes' => $session->opening_notes,
            'closed_at'     => $when($session->closed_at),
            'closed_by'     => $person($session->closer),
            'totals'        => $this->resource['totals'],
            'z_report'      => $latest && ! $latest->isReopened() ? [
                ...$counted($latest),
                'opening_float'   => (string) $latest->opening_float,
                'denominations'   => $latest->denominations,
                'variance_reason' => $latest->variance_reason,
                'notes'           => $latest->notes,
                'closer_reason'   => $latest->closer_reason,
                'totals'          => $latest->totals,
                'needs_approval'  => $latest->needs_approval,
                'approved_by'     => $person($latest->approver),
                'approved_at'     => $when($latest->approved_at),
            ] : null,
            'earlier'       => $session->closings->filter(fn (PosSessionClosing $closing) => $closing->isReopened())->map(fn (PosSessionClosing $closing) => [
                ...$counted($closing),
                'reopened_by'   => $person($closing->reopener),
                'reopened_at'   => $when($closing->reopened_at),
                'reopen_reason' => $closing->reopen_reason,
            ])->values(),
            'own'           => $this->resource['own'],
            'closes'        => $this->resource['closes'],
            'approves'      => $this->resource['approves'],
            'reopens'       => $this->resource['reopens'],
            'denominations' => $this->resource['denominations'],
        ];
    }
}
