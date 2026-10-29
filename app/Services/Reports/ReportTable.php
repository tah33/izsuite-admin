<?php

namespace App\Services\Reports;

/**
 * A report as every way of showing it reads it - the page, its CSV and its
 * PDF (Doc 07 section 4): a title and what it covers, columns, rows, totals,
 * a few headline figures and notes.
 *
 * A column is ['key', 'label', 'type'] - text, number, money, date or
 * percent - and a money column's 'currency': the business's base currency,
 * or null for each row's own (its 'currency'). A row may carry 'link', the
 * page of the record it comes from. A chart, when the report has one, names
 * the column of its labels and the one of its values. A report whose running
 * balance needs its date order is not `sortable`.
 */
final readonly class ReportTable
{
    /**
     * @param  list<array{key: string, label: string, type: string, currency?: ?string}>  $columns
     * @param  list<array<string, mixed>>  $rows
     * @param  array<string, mixed>  $totals
     * @param  list<array{label: string, value: string, currency: string}>  $summary
     * @param  list<string>  $notes
     * @param  array{label: string, value: string}|null  $chart
     */
    public function __construct(
        public string $title,
        public string $period,
        public array $columns,
        public array $rows,
        public array $totals = [],
        public array $summary = [],
        public array $notes = [],
        public ?array $chart = null,
        public bool $sortable = true,
    ) {}
}
