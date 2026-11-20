<?php

namespace App\Services\Reports\Pos;

use App\Enums\TaxTreatment;
use App\Models\User\Workspace;
use App\Services\Reports\ReportTable;

/**
 * Taxes Collected (Doc 18 section 18.36 - Phase H2): the tax POS's completed
 * sales of the period charged - as their invoices kept it - less what their
 * returns' credit notes gave back, by tax, rate and treatment, in the base
 * currency at each sale's rate.
 */
class PosTaxesReport extends PosReport
{
    private const BLANK = ['sales_taxable' => '0.00', 'sales_tax' => '0.00', 'returns_taxable' => '0.00', 'returns_tax' => '0.00'];

    public function build(Workspace $workspace, array $filters): ReportTable
    {
        $base  = $workspace->base_currency;
        $rows  = [];
        $sides = [
            'sales'   => $this->pos->sales($workspace, $filters['from'], $filters['to'], $filters),
            'returns' => $this->pos->returns($workspace, $filters['from'], $filters['to'], $filters),
        ];

        foreach ($sides as $side => $records) {
            foreach ($records as $record) {
                foreach (json_decode((string) $record->taxes, true) ?: [] as $tax) {
                    $key = $this->taxKey($tax);
                    $rows[$key] ??= ['tax' => $this->taxLabel($tax), 'treatment' => (TaxTreatment::tryFrom($tax['treatment'] ?? '') ?? TaxTreatment::Standard)->label(), ...self::BLANK];
                    $rows[$key]["{$side}_taxable"] = $this->add($rows[$key]["{$side}_taxable"], $this->base((string) $tax['taxable_amount'], (string) $record->exchange_rate));
                    $rows[$key]["{$side}_tax"]     = $this->add($rows[$key]["{$side}_tax"], $this->base((string) $tax['tax_amount'], (string) $record->exchange_rate));
                }
            }
        }

        $rows = array_map(fn (array $row) => [
            ...$row,
            'net_taxable' => $this->subtract($row['sales_taxable'], $row['returns_taxable']),
            'net_tax'     => $this->subtract($row['sales_tax'], $row['returns_tax']),
        ], array_values($rows));
        usort($rows, fn (array $a, array $b) => bccomp($b['net_tax'], $a['net_tax'], 2) ?: strcmp($a['tax'], $b['tax']));
        $totals = $this->totals($rows, ['sales_taxable', 'sales_tax', 'returns_taxable', 'returns_tax', 'net_taxable', 'net_tax']);

        return new ReportTable(
            title: $filters['report_name'] ?? 'Taxes Collected',
            period: $this->scope($filters),
            columns: [
                $this->column('tax', 'Tax'),
                $this->column('treatment', 'Treatment'),
                $this->column('sales_taxable', 'Taxable Sales', 'money', $base),
                $this->column('sales_tax', 'Tax Collected', 'money', $base),
                $this->column('returns_taxable', 'Taxable Returns', 'money', $base),
                $this->column('returns_tax', 'Tax Returned', 'money', $base),
                $this->column('net_taxable', 'Net Taxable', 'money', $base),
                $this->column('net_tax', 'Net Tax', 'money', $base),
            ],
            rows: $rows,
            totals: ['tax' => 'Total', ...$totals],
            summary: [
                ['label' => 'Tax Collected', 'value' => $totals['sales_tax'], 'currency' => $base],
                ['label' => 'Tax Returned', 'value' => $totals['returns_tax'], 'currency' => $base],
                ['label' => 'Net Tax', 'value' => $totals['net_tax'], 'currency' => $base],
            ],
            notes: [self::ONCE.' Each tax as the sale\'s invoice kept it, and its return\'s credit note. Tax Summary has every app\'s.'],
        );
    }
}
