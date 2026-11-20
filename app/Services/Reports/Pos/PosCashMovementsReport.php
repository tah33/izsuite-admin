<?php

namespace App\Services\Reports\Pos;

use App\Models\User\PosCashMovement;
use App\Models\User\Workspace;
use App\Services\Reports\ReportTable;

/**
 * The Cash Movement Report (Doc 18 section 18.36 - Phase H3): the cash put
 * into and taken out of the drawers in the period - cash in and out, safe
 * drops, bank deposits, float adjustments and paid outs - by the business's
 * days, oldest first: each its session, kind, amount, the account or payee
 * on the other side, why, who recorded it, and where its approval stands.
 * Neither sales nor expenses (18.38): the totals add completed ones.
 */
class PosCashMovementsReport extends PosReport
{
    private const STATUSES = [PosCashMovement::COMPLETED => 'Completed', PosCashMovement::PENDING => 'Waiting for approval', PosCashMovement::DECLINED => 'Declined'];

    public function build(Workspace $workspace, array $filters): ReportTable
    {
        [$from, $to] = $this->instants($workspace, $filters);
        $rows        = [];

        foreach ($this->pos->cashMovements($workspace, $from, $to, $filters) as $movement) {
            $in     = $movement->direction === PosCashMovement::IN;
            $rows[] = [
                'date'     => $this->dayOf($workspace, $movement->created_at),
                'number'   => $movement->number,
                'register' => $movement->register_name ? "{$movement->register_name} ({$movement->register_code})" : 'Removed',
                'session'  => $movement->session_number,
                'type'     => ucfirst(PosCashMovement::LABELS[$movement->type] ?? $movement->type),
                'currency' => $movement->currency,
                'in'       => $in ? (string) $movement->amount : null,
                'out'      => $in ? null : (string) $movement->amount,
                'other'    => $movement->account_name ?? $movement->payee_name,
                'reason'   => $movement->reason,
                'by'       => $this->person($movement->creator_first_name, $movement->creator_last_name),
                'status'   => (self::STATUSES[$movement->status] ?? ucfirst((string) $movement->status))
                    .($movement->status !== PosCashMovement::PENDING && $movement->decided_by ? ' by '.$this->person($movement->decider_first_name, $movement->decider_last_name) : ''),
                'counts'   => $movement->status === PosCashMovement::COMPLETED,
            ];
        }

        $currency = $this->oneCurrency($rows);
        $done     = array_values(array_filter($rows, fn (array $row) => $row['counts']));
        $sums     = $this->totals($done, ['in', 'out']);

        return new ReportTable(
            title: $filters['report_name'] ?? 'Cash Movement Report',
            period: $this->scope($filters),
            columns: [
                $this->column('date', 'Date', 'date'),
                $this->column('number', 'Number'),
                $this->column('register', 'Register'),
                $this->column('session', 'Session'),
                $this->column('type', 'Kind'),
                $this->column('currency', 'Currency'),
                $this->column('in', 'In', 'money'),
                $this->column('out', 'Out', 'money'),
                $this->column('other', 'Account or Payee'),
                $this->column('reason', 'Why'),
                $this->column('by', 'Recorded By'),
                $this->column('status', 'Status'),
            ],
            rows: array_map(fn (array $row) => array_diff_key($row, ['counts' => true]), $rows),
            totals: $currency ? ['date' => 'Total', 'currency' => $currency, ...$sums] : [],
            summary: $currency ? [
                ['label' => 'Cash In', 'value' => $sums['in'], 'currency' => $currency],
                ['label' => 'Cash Out', 'value' => $sums['out'], 'currency' => $currency],
                ['label' => 'Net', 'value' => $this->subtract($sums['in'], $sums['out']), 'currency' => $currency],
            ] : [],
            notes: [
                'Cash moved into and out of the drawers, each in its register\'s currency: neither sales nor expenses - a paid out\'s expense is in Expenses. The totals add completed movements; one waiting for approval, or declined, moved no cash.',
            ],
        );
    }
}
