<?php

namespace App\Services\Documents;

use App\Models\User\PosSession;
use App\Models\User\PosSessionClosing;
use App\Models\User\Workspace;
use App\Services\Shared\BusinessCalendar;
use App\Support\Decimal;
use Carbon\CarbonInterface;

/**
 * A POS session's X or Z report (Doc 18 sections 18.27 and 18.28 - Phase F3)
 * as its view on the receipt's roll takes it: the session - its register,
 * cashier and times - what it took, and its cash. An X report is the open
 * session's totals as they stand; a Z report, its closing's as they were
 * kept, with the cash counted and the variance.
 */
class PosSessionReportSheet
{
    public function __construct(
        protected BusinessCalendar $calendar,
    ) {}

    /**
     * $totals as PosSessionTotals::of() gives them - an X report's now, or
     * those $closing kept for its Z report. Expects the session's register
     * and cashier loaded.
     */
    public function of(Workspace $workspace, PosSession $session, array $totals, ?PosSessionClosing $closing, DocumentFormat $format): array
    {
        $money = fn ($amount) => $format->money((string) ($amount ?? '0'), $session->currency);
        // What takes cash away, with its minus - none on nothing.
        $less  = fn ($amount) => (Decimal::isZero((string) ($amount ?? '0')) ? '' : '−').$money($amount);
        $zone  = $this->calendar->timezone($workspace);
        $when  = fn (?CarbonInterface $at) => $at ? $format->date($at->copy()->setTimezone($zone)->toDateString()).' '.$at->copy()->setTimezone($zone)->format('H:i') : null;

        return [
            'heading'        => $closing ? 'Z Report' : 'X Report',
            'number'         => $session->number,
            'register'       => "{$session->register->name} ({$session->register->code})",
            'cashier'        => $session->cashier?->name,
            'opened'         => $when($session->opened_at),
            'closed'         => $closing ? $when($closing->closed_at) : null,
            'at'             => $when(now()),
            'salesCount'     => (int) ($totals['sales_count'] ?? 0),
            'salesTotal'     => $money($totals['sales_total'] ?? null),
            'taxTotal'       => $money($totals['tax_total'] ?? null),
            'payments'       => array_map(fn (array $row) => ['method' => $row['method'], 'amount' => $money($row['amount'])], $totals['payments'] ?? []),
            'pending'        => Decimal::isZero((string) ($totals['pending'] ?? '0')) ? null : $money($totals['pending']),
            'openingCash'    => $money($closing ? $closing->opening_float : $session->opening_float),
            'cashSales'      => $money($totals['cash_sales'] ?? null),
            'cashRefunds'    => $less($totals['cash_refunds'] ?? null),
            'cashIn'         => $money($totals['cash_in'] ?? null),
            'cashOut'        => $less($totals['cash_out'] ?? null),
            'expected'       => $money($closing ? $closing->expected_cash : ($totals['expected_cash'] ?? null)),
            'counted'        => $closing ? $money($closing->counted_cash) : null,
            'variance'       => $closing ? $money($closing->variance) : null,
            'varianceReason' => $closing?->variance_reason,
        ];
    }
}
