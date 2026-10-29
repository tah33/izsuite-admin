<?php

namespace App\Services\Reports\Sales;

use App\Enums\DocumentType;
use App\Enums\TaxTreatment;
use App\Models\User\Workspace;
use App\Services\Reports\Report;
use App\Services\Reports\ReportTable;
use App\Support\Decimal;

/**
 * Tax Details (Doc 07 sections 16 and 20, the Tax Summary's drill-down): the
 * tax on each issued invoice and credit note of the period, as the document
 * kept it - in its own currency, and in the base currency at its rate. A
 * credit note takes tax off. It may be narrowed to one treatment, or to one
 * rate (`tax`, a Tax Summary row's key). Each row opens its document.
 */
class TaxDetailsReport extends Report
{
    public function build(Workspace $workspace, array $filters): ReportTable
    {
        $base = $workspace->base_currency;
        $rows = [];

        foreach ([DocumentType::Invoice, DocumentType::CreditNote] as $type) {
            foreach ($this->sales->issued($workspace, $type, $filters['from'], $filters['to'], $filters) as $document) {
                foreach ($document->taxes ?? [] as $tax) {
                    $treatment = TaxTreatment::tryFrom($tax['treatment'] ?? '') ?? TaxTreatment::Standard;

                    if ((($filters['tax'] ?? null) && $this->taxKey($tax) !== $filters['tax'])
                        || (($filters['tax_treatment'] ?? null) && $treatment->value !== $filters['tax_treatment'])) {
                        continue;
                    }

                    $sign = fn (string $amount) => $type === DocumentType::Invoice ? $amount : $this->negative($amount);

                    $rows[] = [
                        'document_date' => $document->document_date,
                        'number'        => $document->number,
                        'type'          => $type->label(),
                        'customer'      => $this->figures->customerName($document),
                        'tax'           => $this->taxLabel($tax),
                        'treatment'     => $treatment->label(),
                        'currency'      => $document->currency,
                        'taxable'       => $sign(Decimal::round(Decimal::of($tax['taxable_amount']))),
                        'tax_amount'    => $sign(Decimal::round(Decimal::of($tax['tax_amount']))),
                        'base_taxable'  => $sign($this->figures->base($tax['taxable_amount'], $document->exchange_rate)),
                        'base_tax'      => $sign($this->figures->base($tax['tax_amount'], $document->exchange_rate)),
                        'link'          => "/sales/{$type->kind()}/{$document->id}",
                    ];
                }
            }
        }

        usort($rows, fn (array $a, array $b) => [$a['document_date'], $a['number']] <=> [$b['document_date'], $b['number']]);

        return new ReportTable(
            title: 'Tax Details',
            period: $this->between($filters).$this->narrowedTo($filters),
            columns: [
                $this->column('document_date', 'Date', 'date'),
                $this->column('number', 'Document'),
                $this->column('type', 'Type'),
                $this->column('customer', 'Customer'),
                $this->column('tax', 'Tax'),
                $this->column('treatment', 'Treatment'),
                $this->column('currency', 'Currency'),
                $this->column('taxable', 'Taxable Amount', 'money'),
                $this->column('tax_amount', 'Tax', 'money'),
                $this->column('base_taxable', "Taxable Amount ({$base})", 'money', $base),
                $this->column('base_tax', "Tax ({$base})", 'money', $base),
            ],
            rows: $rows,
            totals: ['document_date' => 'Total', ...$this->totals($rows, ['base_taxable', 'base_tax'])],
            notes: ["Each document's tax as it kept it, in its own currency and in {$base} at its rate. Credit notes show as negative amounts."],
        );
    }

    /** " · VAT 15%" or " · Exempt" when narrowed to one rate or treatment. */
    private function narrowedTo(array $filters): string
    {
        if ($filters['tax'] ?? null) {
            [$name, $rate, $treatment] = array_pad(explode('|', $filters['tax'], 3), 3, null);

            return ' · '.$this->taxLabel(['name' => $name, 'rate' => $rate, 'treatment' => $treatment]);
        }

        $treatment = TaxTreatment::tryFrom($filters['tax_treatment'] ?? '');

        return $treatment ? ' · '.$treatment->label() : '';
    }
}
