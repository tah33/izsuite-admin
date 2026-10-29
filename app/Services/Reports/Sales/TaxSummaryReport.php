<?php

namespace App\Services\Reports\Sales;

use App\Enums\DocumentType;
use App\Enums\TaxTreatment;
use App\Models\User\SalesPayment;
use App\Models\User\Workspace;
use App\Services\Reports\Report;
use App\Services\Reports\ReportTable;
use App\Support\Decimal;

/**
 * Tax Summary (Doc 07 section 16): the tax each issued invoice charged and
 * each credit note reversed, by rate and treatment as the document kept them
 * - zero-rated, exempt and out-of-scope apart - with tax customers withheld
 * kept separate while withholding applies. In the base currency, at each
 * document's rate. For a business's own review, not a tax return.
 *
 * It may be narrowed to one treatment. A rate's row opens Tax Details, with
 * the documents behind it in their own currency.
 */
class TaxSummaryReport extends Report
{
    public function build(Workspace $workspace, array $filters): ReportTable
    {
        $base = $workspace->base_currency;
        $rows = [];

        foreach ([DocumentType::Invoice, DocumentType::CreditNote] as $type) {
            foreach ($this->sales->issued($workspace, $type, $filters['from'], $filters['to'], $filters) as $document) {
                foreach ($document->taxes ?? [] as $tax) {
                    $key = $this->taxKey($tax);

                    if (($filters['tax_treatment'] ?? null) && ($tax['treatment'] ?? TaxTreatment::Standard->value) !== $filters['tax_treatment']) {
                        continue;
                    }

                    $taxable = $this->figures->base($tax['taxable_amount'], $document->exchange_rate);
                    $amount  = $this->figures->base($tax['tax_amount'], $document->exchange_rate);
                    $side    = $type === DocumentType::Invoice ? 'sales' : 'credit';

                    $rows[$key] ??= $this->row($tax, $key, $filters);
                    $rows[$key]["{$side}_taxable"] = $type === DocumentType::Invoice ? $this->add($rows[$key]["{$side}_taxable"], $taxable) : $this->subtract($rows[$key]["{$side}_taxable"], $taxable);
                    $rows[$key]["{$side}_tax"]     = $type === DocumentType::Invoice ? $this->add($rows[$key]["{$side}_tax"], $amount) : $this->subtract($rows[$key]["{$side}_tax"], $amount);
                    $rows[$key]['net_taxable']     = $this->add($rows[$key]['sales_taxable'], $rows[$key]['credit_taxable']);
                    $rows[$key]['net_tax']         = $this->add($rows[$key]['sales_tax'], $rows[$key]['credit_tax']);
                }
            }
        }

        $rows      = array_values($rows);
        $withheld  = $this->withholdingShown($workspace);
        $netTax    = Decimal::sum(array_column($rows, 'net_tax'));
        $treatment = fn (string $label) => Decimal::sum(array_column(array_filter($rows, fn (array $row) => $row['treatment'] === $label), 'net_taxable'));
        $narrowed  = TaxTreatment::tryFrom($filters['tax_treatment'] ?? '');

        return new ReportTable(
            title: 'Tax Summary',
            period: $this->between($filters).($narrowed ? ' · '.$narrowed->label() : ''),
            columns: [
                $this->column('tax', 'Tax'),
                $this->column('treatment', 'Treatment'),
                $this->column('sales_taxable', 'Invoiced Amount', 'money', $base),
                $this->column('sales_tax', 'Tax on Invoices', 'money', $base),
                $this->column('credit_taxable', 'Credited Amount', 'money', $base),
                $this->column('credit_tax', 'Credit Note Tax', 'money', $base),
                $this->column('net_taxable', 'Net Amount', 'money', $base),
                $this->column('net_tax', 'Net Tax', 'money', $base),
            ],
            rows: $rows,
            totals: ['tax' => 'Total', ...$this->totals($rows, ['sales_taxable', 'sales_tax', 'credit_taxable', 'credit_tax', 'net_taxable', 'net_tax'])],
            summary: array_values(array_filter([
                ['label' => 'Sales Tax on Invoices', 'value' => Decimal::sum(array_column($rows, 'sales_tax')), 'currency' => $base],
                ['label' => 'Credit Note Tax Adjustments', 'value' => Decimal::sum(array_column($rows, 'credit_tax')), 'currency' => $base],
                ['label' => 'Zero-Rated Sales', 'value' => $treatment(TaxTreatment::ZeroRated->label()), 'currency' => $base],
                ['label' => 'Exempt Sales', 'value' => $treatment(TaxTreatment::Exempt->label()), 'currency' => $base],
                ['label' => 'Out-of-Scope Sales', 'value' => $treatment(TaxTreatment::OutOfScope->label()), 'currency' => $base],
                $withheld ? ['label' => 'Withholding Tax Credits', 'value' => $this->withheld($workspace, $filters), 'currency' => $base] : null,
                ['label' => 'Indicative Net Tax Position', 'value' => $netTax, 'currency' => $base],
            ])),
            notes: [
                "In {$base}, from the tax each document kept. For your own review - not an official tax return.",
                'The net tax position is sales tax less credit notes; purchase and expense tax join it with their apps.'.($withheld ? ' Tax customers withheld is kept apart.' : ''),
            ],
        );
    }

    /** Tax customers withheld from completed payments in the period, in the base currency. */
    private function withheld(Workspace $workspace, array $filters): string
    {
        return Decimal::sum($this->sales->moneyMoved($workspace, $filters['from'], $filters['to'], $filters)
            ->where('kind', SalesPayment::PAYMENT)
            ->map(fn (SalesPayment $payment) => $this->figures->base($payment->withholding_amount, $payment->document?->exchange_rate ?? '1')));
    }

    /** A rate's row, opening Tax Details for the same dates and location (section 16: drill-down). */
    private function row(array $tax, string $key, array $filters): array
    {
        $label = $this->taxLabel($tax);

        return [
            'tax'            => $label,
            'treatment'      => (TaxTreatment::tryFrom($tax['treatment'] ?? '') ?? TaxTreatment::Standard)->label(),
            'sales_taxable'  => '0.00',
            'sales_tax'      => '0.00',
            'credit_taxable' => '0.00',
            'credit_tax'     => '0.00',
            'net_taxable'    => '0.00',
            'net_tax'        => '0.00',
            'link'           => '/reports/tax-details?'.http_build_query(array_filter([
                'range'       => 'custom',
                'date_from'   => $filters['from'],
                'date_to'     => $filters['to'],
                'location_id' => $filters['location_id'] ?? null,
                'tax'         => $key,
                'tax_label'   => $label,
            ])),
        ];
    }
}
