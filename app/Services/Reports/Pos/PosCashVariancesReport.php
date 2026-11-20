<?php

namespace App\Services\Reports\Pos;

use App\Models\User\Workspace;
use App\Services\Reports\ReportTable;

/**
 * The Cash Variance Report (Doc 18 section 18.36 - Phase H3): each session
 * closed in the period over or short - by its latest count - oldest first:
 * where, whose, the cash it should have held, what was counted, the
 * difference and why, and who approved one beyond the limit; each in its own
 * currency.
 */
class PosCashVariancesReport extends PosReport
{
    public function build(Workspace $workspace, array $filters): ReportTable
    {
        [$from, $to] = $this->instants($workspace, $filters);
        $rows        = [];

        foreach ($this->pos->variances($workspace, $from, $to, $filters) as $closing) {
            $rows[] = [
                'number'      => $closing->number,
                'register'    => $closing->register_name ? "{$closing->register_name} ({$closing->register_code})" : 'Removed',
                'cashier'     => $this->person($closing->cashier_first_name, $closing->cashier_last_name),
                'closed'      => $this->dayOf($workspace, $closing->closed_at),
                'currency'    => $closing->currency,
                'expected'    => $this->cents($closing->expected_cash),
                'counted'     => $this->cents($closing->counted_cash),
                'variance'    => $this->cents($closing->variance),
                'kind'        => bccomp((string) $closing->variance, '0', 2) > 0 ? 'Over' : 'Short',
                'reason'      => $closing->variance_reason,
                'approval'    => match (true) {
                    $closing->approved_by !== null => 'Approved by '.$this->person($closing->approver_first_name, $closing->approver_last_name),
                    (bool) $closing->needs_approval => 'Waiting for approval',
                    default                         => 'Within the limit',
                },
            ];
        }

        $currency = $this->oneCurrency($rows);
        $over     = array_values(array_filter($rows, fn (array $row) => $row['kind'] === 'Over'));
        $short    = array_values(array_filter($rows, fn (array $row) => $row['kind'] === 'Short'));

        return new ReportTable(
            title: $filters['report_name'] ?? 'Cash Variance Report',
            period: $this->scope($filters),
            columns: [
                $this->column('number', 'Session'),
                $this->column('register', 'Register'),
                $this->column('cashier', 'Cashier'),
                $this->column('closed', 'Closed', 'date'),
                $this->column('currency', 'Currency'),
                $this->column('expected', 'Expected Cash', 'money'),
                $this->column('counted', 'Counted Cash', 'money'),
                $this->column('variance', 'Variance', 'money'),
                $this->column('kind', 'Over or Short'),
                $this->column('reason', 'Why'),
                $this->column('approval', 'Approval'),
            ],
            rows: $rows,
            totals: $currency ? ['number' => 'Total', 'currency' => $currency, ...$this->totals($rows, ['variance'])] : [],
            summary: $currency ? [
                ['label' => 'Over', 'value' => $this->totals($over, ['variance'])['variance'], 'currency' => $currency],
                ['label' => 'Short', 'value' => $this->totals($short, ['variance'])['variance'], 'currency' => $currency],
                ['label' => 'Net Variance', 'value' => $this->totals($rows, ['variance'])['variance'], 'currency' => $currency],
            ] : [],
            notes: [
                'Sessions closed in the period whose count was over or short, by their latest count - a reopened session\'s earlier one no longer stands - each in its register\'s currency. A variance beyond Settings\' limit waits for a supervisor\'s approval.',
            ],
        );
    }
}
