<?php

namespace App\Services\Reports\Sales;

use App\Enums\DocumentType;
use App\Models\User\Workspace;
use App\Services\Reports\Report;
use App\Services\Reports\ReportTable;

/**
 * Credit Notes and Refunds (Doc 16 section 16.26 - Phase H3c): the credit
 * notes issued in the period, by month, customer, salesperson or sales team -
 * a credit note keeps its invoice's - how many, what they credited, and what
 * became of it as it stands now: applied to invoices, its own or others,
 * refunded as money, or still available to the customer. In the base
 * currency, at the rate each note kept.
 */
class CreditRefundsReport extends Report
{
    use GroupsSalesDocuments;

    public const GROUPS = ['month' => 'Month', 'customer' => 'Customer', 'salesperson' => 'Salesperson', 'team' => 'Sales Team'];

    private const MONEY = ['credited', 'applied', 'refunded', 'available'];

    public function build(Workspace $workspace, array $filters): ReportTable
    {
        $base  = $workspace->base_currency;
        $group = array_key_exists($filters['group_by'] ?? '', self::GROUPS) ? $filters['group_by'] : 'month';
        $rows  = $group === 'month' ? $this->months($filters, fn (string $label) => $this->blank($label)) : [];

        foreach ($this->sales->issued($workspace, DocumentType::CreditNote, $filters['from'], $filters['to'], $filters) as $note) {
            [$key, $label] = $this->groupOf($note, $group, $filters);

            $rows[$key] ??= $this->blank($label);
            $rows[$key]['notes']++;

            foreach (['credited' => $note->grand_total, 'applied' => $note->applied_amount, 'refunded' => $note->refunded_amount, 'available' => $note->balance_due] as $figure => $amount) {
                $rows[$key][$figure] = $this->add($rows[$key][$figure], $this->figures->base($amount, $note->exchange_rate));
            }
        }

        if ($group !== 'month') {
            uasort($rows, fn (array $a, array $b) => bccomp($b['credited'], $a['credited'], 2) ?: strcmp($a['group'], $b['group']));
        }

        $rows = array_values($rows);

        return new ReportTable(
            title: 'Credit Notes and Refunds',
            period: $this->between($filters),
            columns: [
                $this->column('group', self::GROUPS[$group]),
                $this->column('notes', 'Credit Notes', 'number'),
                $this->column('credited', 'Credited', 'money', $base),
                $this->column('applied', 'Applied to Invoices', 'money', $base),
                $this->column('refunded', 'Refunded', 'money', $base),
                $this->column('available', 'Still Available', 'money', $base),
            ],
            rows: $rows,
            totals: ['group' => 'Total', 'notes' => array_sum(array_column($rows, 'notes')), ...$this->totals($rows, self::MONEY)],
            notes: ["Credit notes issued in the period, with tax, in {$base} at the rate each kept - and what became of their credit as it stands now: applied to invoices, refunded, or still available to the customer."],
        );
    }

    private function blank(string $label): array
    {
        return ['group' => $label, 'notes' => 0, 'credited' => '0.00', 'applied' => '0.00', 'refunded' => '0.00', 'available' => '0.00'];
    }
}
