<?php

namespace App\Services\Reports\Sales;

use App\Models\User\Workspace;
use App\Services\Reports\ReportTable;

/**
 * Quotation Conversion Rate (Doc 16 section 16.26 - Phase H3a): by month,
 * customer, salesperson or sales team, how many of the quotations that went
 * out in the period were won - accepted or converted - and their share, by
 * number and by value (see QuotationReport).
 */
class QuotationConversionReport extends QuotationReport
{
    public function build(Workspace $workspace, array $filters): ReportTable
    {
        $base = $workspace->base_currency;
        $rows = array_map(fn (array $row) => $this->rated($row), $this->outcomes($workspace, $filters));

        return new ReportTable(
            title: 'Quotation Conversion Rate',
            period: $this->between($filters),
            columns: [
                $this->column('group', self::GROUPS[$this->grouping($filters)]),
                $this->column('quotations', 'Quotations', 'number'),
                $this->column('won', 'Won', 'number'),
                $this->column('rate', 'Conversion Rate', 'percent'),
                $this->column('value', 'Value', 'money', $base),
                $this->column('won_value', 'Value Won', 'money', $base),
                $this->column('value_rate', 'Rate by Value', 'percent'),
            ],
            rows: $rows,
            totals: $this->rated([
                'group'      => 'Total',
                'quotations' => array_sum(array_column($rows, 'quotations')),
                'won'        => array_sum(array_column($rows, 'won')),
                ...$this->totals($rows, ['value', 'won_value']),
            ]),
            notes: ["Of the quotations that went out in the period - not drafts or cancelled ones - those accepted or converted, as each stands now. Values are grand totals in {$base}."],
        );
    }

    /** A group's figures with its shares won: by number, and by value. */
    private function rated(array $row): array
    {
        return [
            'group'      => $row['group'],
            'quotations' => $row['quotations'],
            'won'        => $row['won'],
            'rate'       => $this->percent((string) $row['won'], (string) $row['quotations']),
            'value'      => $row['value'],
            'won_value'  => $row['won_value'],
            'value_rate' => $this->percent($row['won_value'], $row['value']),
        ];
    }
}
