<?php

namespace App\Services\Reports\Sales;

use App\Models\User\Workspace;
use App\Services\Reports\ReportTable;

/**
 * Quotation Performance (Doc 16 section 16.26 - Phase H3a): by month,
 * customer, salesperson or sales team, how many quotations went out in the
 * period and their value, how many were accepted, converted, rejected or
 * expired or are still open, and the value won (see QuotationReport).
 */
class QuotationPerformanceReport extends QuotationReport
{
    private const COUNTS = ['quotations', 'accepted', 'converted', 'rejected', 'expired', 'open'];

    public function build(Workspace $workspace, array $filters): ReportTable
    {
        $base = $workspace->base_currency;
        $rows = array_map(fn (array $row) => array_diff_key($row, ['won' => true]), $this->outcomes($workspace, $filters));

        return new ReportTable(
            title: 'Quotation Performance',
            period: $this->between($filters),
            columns: [
                $this->column('group', self::GROUPS[$this->grouping($filters)]),
                $this->column('quotations', 'Quotations', 'number'),
                $this->column('value', 'Value', 'money', $base),
                $this->column('accepted', 'Accepted', 'number'),
                $this->column('converted', 'Converted', 'number'),
                $this->column('rejected', 'Rejected', 'number'),
                $this->column('expired', 'Expired', 'number'),
                $this->column('open', 'Open', 'number'),
                $this->column('won_value', 'Value Won', 'money', $base),
            ],
            rows: $rows,
            totals: [
                'group' => 'Total',
                ...array_combine(self::COUNTS, array_map(fn (string $key) => array_sum(array_column($rows, $key)), self::COUNTS)),
                ...$this->totals($rows, ['value', 'won_value']),
            ],
            notes: ["Quotations that went out in the period - not drafts or cancelled ones - as each stands now. Values are grand totals in {$base}. Won is accepted or converted; a sent one past its expiry date has expired."],
        );
    }
}
