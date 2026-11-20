<?php

namespace App\Services\Reports\Pos;

use App\Models\User\Workspace;
use App\Services\Reports\ReportTable;

/**
 * Sales by Payment Method (Doc 18 section 18.36 - Phase H1): what each
 * method took for the period's completed sales - their completed payments,
 * after change - and paid back for its returns, each method as the payment
 * or refund named it then, in the base currency; the cash an exchange gave
 * back on a row of its own. Biggest net first.
 */
class PosPaymentMethodsReport extends PosReport
{
    private const BLANK = ['payments' => 0, 'received' => '0.00', 'refunds' => 0, 'refunded' => '0.00'];

    public function build(Workspace $workspace, array $filters): ReportTable
    {
        $base = $workspace->base_currency;
        $rows = [];

        foreach ($this->pos->payments($workspace, $filters['from'], $filters['to'], $filters) as $payment) {
            $key = mb_strtolower($payment->method_name);
            $rows[$key] ??= ['group' => $payment->method_name, ...self::BLANK];
            $rows[$key]['payments']++;
            $rows[$key]['received'] = $this->add($rows[$key]['received'], $this->base($this->cents($payment->amount), (string) $payment->exchange_rate));
        }

        foreach ($this->pos->returns($workspace, $filters['from'], $filters['to'], $filters) as $return) {
            if (bccomp((string) $return->refunded, '0', 2) > 0) {
                $this->refund($rows, mb_strtolower((string) $return->refund_method_name), $return->refund_method_name ?? 'Removed', $this->base((string) $return->refunded, (string) $return->exchange_rate));
            }
        }

        foreach ($this->pos->sales($workspace, $filters['from'], $filters['to'], $filters) as $sale) {
            if (bccomp((string) $sale->exchange_refunded, '0', 2) > 0) {
                $this->refund($rows, "\0exchange", 'Cash paid back on exchanges', $this->base((string) $sale->exchange_refunded, (string) $sale->exchange_rate));
            }
        }

        $rows = array_map(fn (array $row) => [...$row, 'net' => $this->subtract($row['received'], $row['refunded'])], array_values($rows));
        usort($rows, fn (array $a, array $b) => bccomp($b['net'], $a['net'], 2) ?: strcmp($a['group'], $b['group']));

        return new ReportTable(
            title: $filters['report_name'] ?? 'Sales by Payment Method',
            period: $this->scope($filters),
            columns: [
                $this->column('group', 'Payment Method'),
                $this->column('payments', 'Payments', 'number'),
                $this->column('received', 'Received', 'money', $base),
                $this->column('refunds', 'Refunds', 'number'),
                $this->column('refunded', 'Paid Back', 'money', $base),
                $this->column('net', 'Net', 'money', $base),
            ],
            rows: $rows,
            totals: ['group' => 'Total', 'payments' => array_sum(array_column($rows, 'payments')), 'refunds' => array_sum(array_column($rows, 'refunds')), ...$this->totals($rows, ['received', 'refunded', 'net'])],
            notes: [
                'The completed payments of completed POS sales, after change, by the date of their sale\'s invoice; refunds paid back for the returns of the period. Pending and declined payments and refunds never count.',
                'A credit sale, and the credit an exchange used, are not payments: what a sale still owes is on its invoice.',
            ],
        );
    }

    /** $amount paid back by the method keyed $key. */
    private function refund(array &$rows, string $key, string $label, string $amount): void
    {
        $rows[$key] ??= ['group' => $label, ...self::BLANK];
        $rows[$key]['refunds']++;
        $rows[$key]['refunded'] = $this->add($rows[$key]['refunded'], $amount);
    }
}
