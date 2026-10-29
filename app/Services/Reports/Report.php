<?php

namespace App\Services\Reports;

use App\Enums\TaxTreatment;
use App\Models\User\Workspace;
use App\Repositories\User\SalesReportRepository;
use App\Support\Decimal;

/**
 * One of Basic Reports (Doc 07): built from the source records each time it
 * is asked for - never stored (section 19) - with the filters ReportService
 * has worked out: from, to, as_of, group_by, location_id, contact_id,
 * currency and those a report takes of its own (item_id, category_id,
 * payment_status, payment_account_id, tax_treatment, tax).
 */
abstract class Report
{
    public function __construct(
        protected SalesReportRepository $sales,
        protected SalesFigures $figures,
        protected ReportPeriod $period,
    ) {}

    abstract public function build(Workspace $workspace, array $filters): ReportTable;

    /** A column: text, number, money, date or percent; a money column in $currency, or each row's own when null. */
    protected function column(string $key, string $label, string $type = 'text', ?string $currency = null): array
    {
        return ['key' => $key, 'label' => $label, 'type' => $type, 'currency' => $currency];
    }

    /** "1 Oct 2026 - 31 Oct 2026". */
    protected function between(array $filters): string
    {
        return $this->day($filters['from']).' - '.$this->day($filters['to']);
    }

    protected function day(string $date): string
    {
        return date('j M Y', strtotime($date));
    }

    /**
     * Whether tax columns show (section 16): while the business uses taxes,
     * or has tax on documents from before it stopped.
     */
    protected function taxesShown(Workspace $workspace): bool
    {
        return $workspace->usesTaxes() || $this->sales->hasTaxHistory($workspace);
    }

    /** Whether tax withheld shows: while withholding is on, or anything was withheld. */
    protected function withholdingShown(Workspace $workspace): bool
    {
        return (bool) $workspace->withholding_tax || $this->sales->hasWithholdingHistory($workspace);
    }

    /**
     * Drill Down (section 4) from a row that sums documents: the Documents
     * page with the issued invoices and credit notes behind it - its dates,
     * the report's location and currency, and what the row narrows them to.
     */
    protected function documentsLink(string $from, string $to, array $filters, array $narrower = []): string
    {
        return '/documents?'.http_build_query(array_filter([
            'status'      => 'issued',
            'date_from'   => $from,
            'date_to'     => $to,
            'location_id' => $filters['location_id'] ?? null,
            'currency'    => $filters['currency'] ?? null,
            ...$narrower,
        ]));
    }

    /** A tax a document kept, as one key: its name, rate and treatment - what Tax Summary rows and Tax Details share. */
    protected function taxKey(array $tax): string
    {
        return implode('|', [$tax['name'], $tax['rate'], $tax['treatment'] ?? TaxTreatment::Standard->value]);
    }

    /** "VAT 15%" for a charged rate; the tax's name - "Exempt" - for one that charges nothing. */
    protected function taxLabel(array $tax): string
    {
        $treatment = TaxTreatment::tryFrom($tax['treatment'] ?? '') ?? TaxTreatment::Standard;
        $rate      = str_contains((string) $tax['rate'], '.') ? rtrim(rtrim((string) $tax['rate'], '0'), '.') : (string) $tax['rate'];

        return $treatment->charges() ? "{$tax['name']} {$rate}%" : $tax['name'];
    }

    /** Each of $keys summed over $rows, to the cent. */
    protected function totals(array $rows, array $keys): array
    {
        return collect($keys)->mapWithKeys(fn (string $key) => [$key => Decimal::sum(array_column($rows, $key))])->all();
    }

    protected function add(string $a, string $b): string
    {
        return bcadd($a, $b, 2);
    }

    protected function subtract(string $a, string $b): string
    {
        return bcsub($a, $b, 2);
    }

    protected function negative(string $amount): string
    {
        return bcsub('0', $amount, 2);
    }
}
