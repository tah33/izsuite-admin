<?php

namespace App\Services\Reports\Crm;

use App\Models\User\Workspace;
use App\Repositories\User\CrmOpportunityRepository;
use App\Repositories\User\CrmPipelineRepository;
use App\Repositories\User\ExpenseReportRepository;
use App\Repositories\User\PurchaseReportRepository;
use App\Repositories\User\SalesReportRepository;
use App\Services\Api\Crm\CrmForecastService;
use App\Services\Crm\OpportunityForecast;
use App\Services\Crm\OpportunityVisibility;
use App\Services\Reports\ReportPeriod;
use App\Services\Reports\ReportTable;
use App\Services\Reports\SalesFigures;
use App\Services\Shared\BusinessCalendar;

/**
 * Pipeline Value and Weighted Forecast (Doc 17 sections 17.14 and 17.29 -
 * Phase H2), as the Forecast page works them out (CrmForecastService, Phase
 * G2): the open opportunities the user sees now, by stage, expected closing
 * month (Overdue, this month and the next 11, then Later), owner or sales
 * team. Pipeline Value shows their expected value and each group's share of
 * it, by stage unless told; Weighted Forecast their expected and weighted
 * value with what was won this month, by month unless told. The report's
 * key says which (ReportService).
 */
class ForecastReport extends OpportunityReport
{
    /** How each can group them. */
    private const GROUPS_SHOWN = ['stage' => 'Stage', 'month' => 'Closing Month', 'owner' => 'Owner', 'team' => 'Sales Team'];

    public function __construct(
        SalesReportRepository $sales,
        SalesFigures $figures,
        ReportPeriod $period,
        PurchaseReportRepository $purchases,
        ExpenseReportRepository $expenses,
        CrmOpportunityRepository $opportunities,
        CrmPipelineRepository $pipelines,
        OpportunityVisibility $visibility,
        OpportunityForecast $forecast,
        BusinessCalendar $calendar,
        protected CrmForecastService $forecastPage,
    ) {
        parent::__construct($sales, $figures, $period, $purchases, $expenses, $opportunities, $pipelines, $visibility, $forecast, $calendar);
    }

    public function build(Workspace $workspace, array $filters): ReportTable
    {
        $weighted = ($filters['report_key'] ?? '') === 'weighted-forecast';
        $by       = array_key_exists($filters['group_by'], self::GROUPS_SHOWN) ? $filters['group_by'] : ($weighted ? 'month' : 'stage');
        $base     = $workspace->base_currency;
        $forecast = $this->forecastPage->rows($workspace, $filters['user'], ['group_by' => $by, ...$this->narrowing($filters)]);
        $total    = $forecast['total'];
        // A stage, owner or team with nothing open holds only this month's won value: the pipeline has none of it.
        $groups   = $weighted || $by === 'month' ? $forecast['rows'] : array_values(array_filter($forecast['rows'], fn (array $row) => $row['count'] > 0));
        $figures  = fn (array $row) => $weighted
            ? ['count' => $row['count'], 'value' => $row['value']['total'], 'weighted' => $row['weighted']['total'], 'won' => $row['won']['total']]
            : ['count' => $row['count'], 'value' => $row['value']['total'], 'share' => $this->percent($row['value']['total'], $total['value']['total'])];

        return new ReportTable(
            title: $filters['report_name'] ?? ($weighted ? 'Weighted Forecast' : 'Pipeline Value'),
            period: 'As of '.$this->day($this->period->today($workspace), $filters),
            columns: [
                $this->column('group', self::GROUPS_SHOWN[$by]),
                $this->column('count', 'Opportunities', 'number'),
                ...($weighted
                    ? [$this->column('value', 'Expected Value', 'money', $base), $this->column('weighted', 'Weighted Forecast', 'money', $base), $this->column('won', 'Won This Month', 'money', $base)]
                    : [$this->column('value', 'Pipeline Value', 'money', $base), $this->column('share', 'Share', 'percent')]),
            ],
            rows: array_map(fn (array $row) => ['group' => $row['label'], ...$figures($row)], $groups),
            totals: ['group' => 'Total', ...$figures($total)],
            notes: array_values(array_filter([
                $this->counting($filters, false).($weighted
                    ? ": their expected value and weighted forecast - expected value × probability - in {$base} at today's rates, with what was won this month. Overdue holds those past their expected closing day; Later those closing after the twelve months. It is no cash-flow or financial forecast."
                    : ": their expected value - before tax - in {$base} at today's rates, and each group's share of the whole."),
                $this->unratedNote($total['value']['unrated']),
                self::NOT_SALES,
            ])),
            chart: ['label' => 'group', 'value' => $weighted ? 'weighted' : 'value'],
        );
    }
}
