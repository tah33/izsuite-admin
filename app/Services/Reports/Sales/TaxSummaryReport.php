<?php

namespace App\Services\Reports\Sales;

use App\Enums\DocumentType;
use App\Enums\TaxTreatment;
use App\Models\User\SalesPayment;
use App\Models\User\SupplierPayment;
use App\Models\User\Workspace;
use App\Services\Reports\Report;
use App\Services\Reports\ReportTable;
use App\Services\User\Apps\WorkspaceApps;
use App\Support\Decimal;

/**
 * Tax Summary (Doc 07 section 16): the tax each issued invoice charged, each
 * credit note reversed and each recorded purchase paid, by rate and
 * treatment as the document kept them - zero-rated, exempt and out-of-scope
 * apart - with tax withheld from payments kept separate while withholding
 * applies. Purchase tax that can be claimed back comes off the net tax
 * position; tax that cannot is shown but not taken off. In the base
 * currency, at each document's rate. For a business's own review, not a tax
 * return.
 *
 * It may be narrowed to one treatment. A rate's row opens Tax Details, with
 * the documents behind it in their own currency.
 */
class TaxSummaryReport extends Report
{
    public function build(Workspace $workspace, array $filters): ReportTable
    {
        $base   = $workspace->base_currency;
        $bought = WorkspaceApps::for($workspace)->hasHistory('purchases');
        $rows   = [];

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
                }
            }
        }

        $kept = $bought ? $this->purchaseTax($workspace, $filters, $rows) : [];
        $rows = array_map(fn (array $row) => [
            ...$row,
            'net_tax' => $this->subtract($this->add($row['sales_tax'], $row['credit_tax']), $kept[$row['key']] ?? '0.00'),
        ], $rows);

        $rows      = array_values(array_map(fn (array $row) => array_diff_key($row, ['key' => true]), $rows));
        $withheld  = $this->withholdingShown($workspace);
        $netTax    = Decimal::sum(array_column($rows, 'net_tax'));
        $treatment = fn (string $label) => Decimal::sum(array_column(array_filter($rows, fn (array $row) => $row['treatment'] === $label), 'net_taxable'));
        $narrowed  = TaxTreatment::tryFrom($filters['tax_treatment'] ?? '');

        return new ReportTable(
            title: 'Tax Summary',
            period: $this->between($filters).($narrowed ? ' · '.$narrowed->label() : ''),
            columns: array_values(array_filter([
                $this->column('tax', 'Tax'),
                $this->column('treatment', 'Treatment'),
                $this->column('sales_taxable', 'Invoiced Amount', 'money', $base),
                $this->column('sales_tax', 'Tax on Invoices', 'money', $base),
                $this->column('credit_taxable', 'Credited Amount', 'money', $base),
                $this->column('credit_tax', 'Credit Note Tax', 'money', $base),
                $this->column('net_taxable', 'Net Sales Amount', 'money', $base),
                $bought ? $this->column('purchase_taxable', 'Purchased Amount', 'money', $base) : null,
                $bought ? $this->column('purchase_tax', 'Tax on Purchases', 'money', $base) : null,
                $this->column('net_tax', 'Net Tax', 'money', $base),
            ])),
            rows: $rows,
            totals: ['tax' => 'Total', ...$this->totals($rows, ['sales_taxable', 'sales_tax', 'credit_taxable', 'credit_tax', 'net_taxable', 'purchase_taxable', 'purchase_tax', 'net_tax'])],
            summary: array_values(array_filter([
                ['label' => 'Sales Tax on Invoices', 'value' => Decimal::sum(array_column($rows, 'sales_tax')), 'currency' => $base],
                ['label' => 'Credit Note Tax Adjustments', 'value' => Decimal::sum(array_column($rows, 'credit_tax')), 'currency' => $base],
                $bought ? ['label' => 'Purchase Tax', 'value' => Decimal::sum(array_column($rows, 'purchase_tax')), 'currency' => $base] : null,
                $bought ? ['label' => 'Purchase Tax Not Claimable', 'value' => bcsub(Decimal::sum(array_column($rows, 'purchase_tax')), Decimal::sum($kept), 2), 'currency' => $base] : null,
                ['label' => 'Zero-Rated Sales', 'value' => $treatment(TaxTreatment::ZeroRated->label()), 'currency' => $base],
                ['label' => 'Exempt Sales', 'value' => $treatment(TaxTreatment::Exempt->label()), 'currency' => $base],
                ['label' => 'Out-of-Scope Sales', 'value' => $treatment(TaxTreatment::OutOfScope->label()), 'currency' => $base],
                $withheld ? ['label' => 'Withholding Tax Credits', 'value' => $this->withheld($workspace, $filters), 'currency' => $base] : null,
                $withheld && $bought ? ['label' => 'Tax Withheld from Suppliers', 'value' => $this->withheldFromSuppliers($workspace, $filters), 'currency' => $base] : null,
                ['label' => 'Indicative Net Tax Position', 'value' => $netTax, 'currency' => $base],
            ])),
            notes: [
                "In {$base}, from the tax each document kept. For your own review - not an official tax return.",
                ($bought
                    ? 'The net tax position is sales tax less credit notes, less purchase tax that can be claimed back; expense tax joins it with its app.'
                    : 'The net tax position is sales tax less credit notes; purchase and expense tax join it with their apps.').($withheld ? ' Tax withheld from payments is kept apart.' : ''),
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

    /**
     * Each recorded purchase's tax into its rate's row - adding rows for rates
     * only purchases use - and, by rate key, the part that can be claimed back:
     * all of it unless its lines kept the rate as not recoverable.
     */
    private function purchaseTax(Workspace $workspace, array $filters, array &$rows): array
    {
        $unclaimable = $this->purchases->recordedLines($workspace, $filters['from'], $filters['to'], $filters)
            ->filter(fn ($line) => $line->tax_name !== null && $line->tax_recoverability === 'non_recoverable')
            ->map(fn ($line) => $this->taxKey(['name' => $line->tax_name, 'rate' => $line->tax_rate, 'treatment' => $line->tax_treatment]))
            ->unique()
            ->all();
        $claimable = [];

        foreach ($this->purchases->recorded($workspace, $filters['from'], $filters['to'], $filters) as $purchase) {
            foreach ($purchase->taxes ?? [] as $tax) {
                $key = $this->taxKey($tax);

                if (($filters['tax_treatment'] ?? null) && ($tax['treatment'] ?? TaxTreatment::Standard->value) !== $filters['tax_treatment']) {
                    continue;
                }

                $amount = $this->figures->base($tax['tax_amount'], $purchase->exchange_rate);

                $rows[$key] ??= $this->row($tax, $key, $filters);
                $rows[$key]['purchase_taxable'] = $this->add($rows[$key]['purchase_taxable'], $this->figures->base($tax['taxable_amount'], $purchase->exchange_rate));
                $rows[$key]['purchase_tax']     = $this->add($rows[$key]['purchase_tax'], $amount);

                if (! in_array($key, $unclaimable, true)) {
                    $claimable[$key] = $this->add($claimable[$key] ?? '0.00', $amount);
                }
            }
        }

        return $claimable;
    }

    /** Tax withheld from completed supplier payments in the period - owed to the tax office - in the base currency. */
    private function withheldFromSuppliers(Workspace $workspace, array $filters): string
    {
        return Decimal::sum($this->purchases->paymentsMade($workspace, $filters['from'], $filters['to'], $filters)
            ->map(fn (SupplierPayment $payment) => $this->figures->base($payment->withholding_amount, $payment->purchase?->exchange_rate ?? '1')));
    }

    /** A rate's row, opening Tax Details for the same dates and location (section 16: drill-down). */
    private function row(array $tax, string $key, array $filters): array
    {
        $label = $this->taxLabel($tax);

        return [
            'key'              => $key,
            'tax'              => $label,
            'treatment'        => (TaxTreatment::tryFrom($tax['treatment'] ?? '') ?? TaxTreatment::Standard)->label(),
            'sales_taxable'    => '0.00',
            'sales_tax'        => '0.00',
            'credit_taxable'   => '0.00',
            'credit_tax'       => '0.00',
            'net_taxable'      => '0.00',
            'purchase_taxable' => '0.00',
            'purchase_tax'     => '0.00',
            'net_tax'          => '0.00',
            'link'             => '/reports/tax-details?'.http_build_query(array_filter([
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
