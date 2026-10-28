<?php

namespace App\Services\Reports;

use App\Models\User\Workspace;
use App\Repositories\User\SalesReportRepository;
use App\Support\Decimal;

/**
 * One of Basic Reports (Doc 07): built from the source records each time it
 * is asked for - never stored (section 19) - with the filters ReportService
 * has worked out: from, to, as_of, group_by, location_id, contact_id and
 * currency.
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
