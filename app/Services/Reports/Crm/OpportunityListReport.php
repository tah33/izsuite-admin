<?php

namespace App\Services\Reports\Crm;

use App\Models\User\CrmOpportunity;
use App\Models\User\Workspace;
use App\Repositories\User\CrmActivityRepository;
use App\Repositories\User\CrmEmailRepository;
use App\Repositories\User\CrmOpportunityRepository;
use App\Repositories\User\CrmPipelineRepository;
use App\Repositories\User\ExpenseReportRepository;
use App\Repositories\User\PurchaseReportRepository;
use App\Repositories\User\SalesReportRepository;
use App\Services\Crm\FollowUpCutoffs;
use App\Services\Crm\OpportunityForecast;
use App\Services\Crm\OpportunityVisibility;
use App\Services\Reports\ReportPeriod;
use App\Services\Reports\ReportTable;
use App\Services\Reports\SalesFigures;
use App\Services\Shared\BusinessCalendar;
use App\Support\Decimal;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Two lists of the open opportunities the user sees now (Doc 17 section
 * 17.29), each row opening its opportunity, its values in its own currency
 * and their sum in the base currency above:
 * - Opportunities Closing Soon (Phase H2): expected to close from today to
 *   the end of the closing-soon period (FollowUpCutoffs, as the Overview
 *   and the Dashboard count them), the soonest first;
 * - Opportunities Without Follow-up (Phase H3): with no activity planned,
 *   as the Overview's warning counts them (Phase G3b) - their last contact
 *   and full days without activity as on an opportunity's page (Phase G3a),
 *   the longest without first.
 * The report's key says which (ReportService).
 */
class OpportunityListReport extends OpportunityReport
{
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
        protected FollowUpCutoffs $cutoffs,
        protected CrmActivityRepository $activities,
        protected CrmEmailRepository $emails,
    ) {
        parent::__construct($sales, $figures, $period, $purchases, $expenses, $opportunities, $pipelines, $visibility, $forecast, $calendar);
    }

    public function build(Workspace $workspace, array $filters): ReportTable
    {
        return ($filters['report_key'] ?? '') === 'opportunities-without-follow-up'
            ? $this->withoutFollowUp($workspace, $filters)
            : $this->closingSoon($workspace, $filters);
    }

    private function closingSoon(Workspace $workspace, array $filters): ReportTable
    {
        ['closing_from' => $from, 'closing_to' => $to] = $this->cutoffs->closingSoon($workspace);

        $rows    = $this->open($workspace, $filters)
            ->filter(fn (CrmOpportunity $row) => $row->expected_close_date && $row->expected_close_date->toDateString() >= $from && $row->expected_close_date->toDateString() <= $to)
            ->sortBy(fn (CrmOpportunity $row) => [$row->expected_close_date->toDateString(), $row->id])
            ->values();
        $today   = CarbonImmutable::parse($from);
        $several = $this->severalPipelines($workspace, $filters);

        return $this->listed($workspace, $filters, $rows, [
            $this->column('close', 'Expected Close', 'date'),
            $this->column('days_left', 'Days Left', 'number'),
            $this->column('probability', 'Probability', 'percent'),
            $this->column('value', 'Expected Value', 'money'),
            $this->column('weighted', 'Weighted Value', 'money'),
        ], fn (CrmOpportunity $row) => [
            ...$this->named($row, $several),
            'close'       => $row->expected_close_date->toDateString(),
            'days_left'   => (int) round($today->diffInDays(CarbonImmutable::parse($row->expected_close_date->toDateString()))),
            'probability' => (string) $row->probability,
            'value'       => Decimal::round((string) $row->expected_value),
            'weighted'    => Decimal::round(bcdiv(bcmul((string) $row->expected_value, (string) $row->probability, 4), '100', 4)),
        ], "The open opportunities you see now expected to close from today to {$this->day($to, $filters)} - the closing-soon period in Settings → CRM - archived ones left out, the soonest first.");
    }

    private function withoutFollowUp(Workspace $workspace, array $filters): ReportTable
    {
        $rows    = $this->open($workspace, $filters, true);
        $ids     = $rows->pluck('id')->all();
        $done    = $ids ? $this->activities->lastDoneByOpportunity($workspace, $ids) : [];
        $sent    = $ids ? $this->emails->lastSentByOpportunity($workspace, $ids) : [];
        $zone    = $this->calendar->timezone($workspace);
        $now     = now();
        $several = $this->severalPipelines($workspace, $filters);
        $last    = fn (CrmOpportunity $row) => collect([$done[$row->id] ?? null, $sent[$row->id] ?? null])->filter()
            ->map(fn (string $at) => CarbonImmutable::parse($at))
            ->sortByDesc(fn (CarbonImmutable $at) => $at->getTimestamp())
            ->first();
        $quiet   = fn (CrmOpportunity $row) => intdiv(max(0, $now->getTimestamp() - ($last($row) ?? $row->created_at)->getTimestamp()), 86400);
        $rows    = $rows->sortBy(fn (CrmOpportunity $row) => [-$quiet($row), $row->id])->values();

        return $this->listed($workspace, $filters, $rows, [
            $this->column('close', 'Expected Close', 'date'),
            $this->column('value', 'Expected Value', 'money'),
            $this->column('last_contact', 'Last Contact', 'date'),
            $this->column('days', 'Days Without Activity', 'number'),
        ], fn (CrmOpportunity $row) => [
            ...$this->named($row, $several),
            'close'        => $row->expected_close_date?->toDateString(),
            'value'        => Decimal::round((string) $row->expected_value),
            'last_contact' => $last($row)?->setTimezone($zone)->toDateString(),
            'days'         => $quiet($row),
        ], 'The open opportunities you see now with no activity planned, archived ones left out, the longest without activity first. Their last contact is the latest activity done or e-mail sent from izSuite; their days without activity are full days since it, or since they were added.');
    }

    /**
     * The list of $rows: the opportunity, its contact, stage and owner, then
     * $columns as $row gives them - each row opening its opportunity - with
     * the values' sum in the base currency above.
     */
    private function listed(Workspace $workspace, array $filters, Collection $rows, array $columns, callable $row, string $note): ReportTable
    {
        $base   = $workspace->base_currency;
        $values = $this->forecast->baseValues($workspace, $rows);
        $value  = $this->sum($rows, $values);
        $count  = $rows->count();

        return new ReportTable(
            title: $filters['report_name'] ?? 'Opportunities',
            period: 'As of '.$this->day($this->period->today($workspace), $filters),
            columns: [
                $this->column('opportunity', 'Opportunity'),
                $this->column('contact', 'Contact'),
                $this->column('stage', 'Stage'),
                $this->column('owner', 'Owner'),
                $this->column('currency', 'Currency'),
                ...$columns,
            ],
            rows: $rows->map(fn (CrmOpportunity $opportunity) => [...$row($opportunity), 'currency' => $opportunity->currency, 'link' => "/crm/opportunities/{$opportunity->id}"])->all(),
            summary: [
                ['label' => $count === 1 ? '1 opportunity, expected' : "{$count} opportunities, expected", 'value' => $value['total'], 'currency' => $base],
                ['label' => 'Weighted', 'value' => $this->sum($rows, $values, 'weighted')['total'], 'currency' => $base],
            ],
            notes: array_values(array_filter([$note, "Values are in each opportunity's currency; the sums above in {$base} at today's rates.", $this->unratedNote($value['unrated']), self::NOT_SALES])),
        );
    }

    /** @return array{opportunity: string, contact: ?string, stage: string, owner: ?string} what names $row */
    private function named(CrmOpportunity $row, bool $several): array
    {
        return ['opportunity' => $row->name, 'contact' => $row->contact?->name, 'stage' => $this->stageName($row->stage, $several), 'owner' => $row->owner?->name];
    }
}
