<?php

namespace App\Services\Reports\Sales;

use App\Models\User\Workspace;
use App\Services\Reports\ReportTable;

/**
 * Sales Order Backlog (Doc 16 section 16.26 - Phase H3b): the open sales
 * orders grouped by the month they were ordered, customer, salesperson or
 * sales team - how many, their value, and what is left of them to deliver
 * and to invoice (see OrderReport). Months come in order; the other
 * groupings put the most left to invoice first.
 */
class OrderBacklogReport extends OrderReport
{
    use GroupsSalesDocuments;

    public const GROUPS = ['month' => 'Month Ordered', 'customer' => 'Customer', 'salesperson' => 'Salesperson', 'team' => 'Sales Team'];

    private const MONEY = ['value', 'to_deliver', 'to_invoice'];

    public function build(Workspace $workspace, array $filters): ReportTable
    {
        $base  = $workspace->base_currency;
        $group = array_key_exists($filters['group_by'] ?? '', self::GROUPS) ? $filters['group_by'] : 'month';
        $rows  = [];

        foreach ($this->sales->openOrders($workspace, $filters) as $order) {
            [$key, $label] = $this->groupOf($order, $group, $filters);

            $rows[$key] ??= ['group' => $label, 'orders' => 0, 'value' => '0.00', 'to_deliver' => '0.00', 'to_invoice' => '0.00'];
            $rows[$key]['orders']++;

            foreach ($this->left($order) as $figure => $amount) {
                $rows[$key][$figure] = $this->add($rows[$key][$figure], $amount);
            }
        }

        if ($group === 'month') {
            ksort($rows);
        } else {
            uasort($rows, fn (array $a, array $b) => bccomp($b['to_invoice'], $a['to_invoice'], 2) ?: strcmp($a['group'], $b['group']));
        }

        $rows = array_values($rows);

        return new ReportTable(
            title: 'Sales Order Backlog',
            period: 'Open on '.$this->day($filters['as_of'], $filters),
            columns: [
                $this->column('group', self::GROUPS[$group]),
                $this->column('orders', 'Orders', 'number'),
                $this->column('value', 'Order Value', 'money', $base),
                $this->column('to_deliver', 'Left to Deliver', 'money', $base),
                $this->column('to_invoice', 'Left to Invoice', 'money', $base),
            ],
            rows: $rows,
            totals: ['group' => 'Total', 'orders' => array_sum(array_column($rows, 'orders')), ...$this->totals($rows, self::MONEY)],
            notes: ["Confirmed and held sales orders as they stand now, before tax in {$base}. Left to deliver is their goods not delivered yet; left to invoice, what their ordered quantities have not been invoiced yet."],
        );
    }
}
