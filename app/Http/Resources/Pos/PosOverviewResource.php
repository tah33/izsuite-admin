<?php

namespace App\Http\Resources\Pos;

use App\Models\User\PosSession;
use App\Support\Decimal;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * POS today (Doc 18 Phase G2), as its Overview and the Dashboard's POS card
 * show it - PosOverviewService::today(): the day and the base currency, the
 * sales, payments by method, returns, variances, top items and the open
 * sessions.
 */
class PosOverviewResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $today = $this->resource;

        return [
            'date'          => $today['date'],
            'currency'      => $today['currency'],
            'sales'         => $today['sales'],
            'payments'      => $today['payments'],
            'returns'       => $today['returns'],
            'variances'     => array_map(fn ($row) => [
                'session' => ['id' => (int) $row->id, 'number' => $row->number], 'register' => "{$row->register} ({$row->code})", 'currency' => $row->currency, 'variance' => Decimal::round((string) $row->variance),
            ], $today['variances']),
            'top_items'     => array_map(fn ($row) => ['name' => $row->name, 'quantity' => bcadd((string) $row->quantity, '0', 3), 'amount' => Decimal::round((string) $row->amount)], $today['top_items']),
            'open_sessions' => array_map(fn (PosSession $session) => [
                'id' => $session->id, 'number' => $session->number, 'register' => $session->register ? "{$session->register->name} ({$session->register->code})" : null,
                'cashier' => $session->cashier?->name, 'opened_at' => $session->opened_at?->toIso8601String(),
            ], $today['open_sessions']),
        ];
    }
}
