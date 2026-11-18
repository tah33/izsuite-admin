<?php

namespace App\Services\Reports\Crm;

use App\Models\User\CrmOpportunity;
use App\Models\User\CrmPipeline;
use App\Models\User\CrmStage;
use App\Models\User\Workspace;
use App\Repositories\User\CrmOpportunityRepository;
use App\Repositories\User\CrmPipelineRepository;
use App\Repositories\User\ExpenseReportRepository;
use App\Repositories\User\PurchaseReportRepository;
use App\Repositories\User\SalesReportRepository;
use App\Services\Crm\OpportunityForecast;
use App\Services\Crm\OpportunityVisibility;
use App\Services\Reports\Report;
use App\Services\Reports\ReportPeriod;
use App\Services\Reports\SalesFigures;
use App\Services\Shared\BusinessCalendar;
use App\Support\Decimal;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * CRM's opportunity reports (Doc 17 section 17.29 - Phase H2), each built
 * from the opportunities the user sees (OpportunityVisibility), archived ones
 * left out as on the Overview, narrowed by location, sales team, owner, lead
 * source, pipeline and stage: the open ones as they stand now, or those won
 * or lost in the range - its days the business's. Values are in the base
 * currency at today's rates (OpportunityForecast): estimates, those of a
 * currency without a rate today left out and counted. Opportunities are not
 * sales (17.31).
 */
abstract class OpportunityReport extends Report
{
    /** How the grouped opportunity reports can group them, by month unless told. */
    public const GROUPS = ['month' => 'Month', 'owner' => 'Owner', 'team' => 'Sales Team', 'source' => 'Lead Source', 'stage' => 'Stage'];

    /** The note every opportunity report ends with. */
    protected const NOT_SALES = 'Opportunities are estimates, not sales: only issued invoices count as sales.';

    /** The business's pipelines with their stages, once read. */
    private ?Collection $pipelinesRead = null;

    public function __construct(
        SalesReportRepository $sales,
        SalesFigures $figures,
        ReportPeriod $period,
        PurchaseReportRepository $purchases,
        ExpenseReportRepository $expenses,
        protected CrmOpportunityRepository $opportunities,
        protected CrmPipelineRepository $pipelines,
        protected OpportunityVisibility $visibility,
        protected OpportunityForecast $forecast,
        protected BusinessCalendar $calendar,
    ) {
        parent::__construct($sales, $figures, $period, $purchases, $expenses);
    }

    /** The open ones the user sees, narrowed by the report's filters - only those with no activity planned when $withoutNextActivity. */
    protected function open(Workspace $workspace, array $filters, bool $withoutNextActivity = false): Collection
    {
        return $this->opportunities->forReports($workspace, $this->visibility->scopeFor($workspace, $filters['user']), $this->narrowing($filters), null, null, $withoutNextActivity);
    }

    /** Those the user sees won or lost in the range, narrowed by the report's filters. */
    protected function closed(Workspace $workspace, array $filters): Collection
    {
        return $this->opportunities->forReports($workspace, $this->visibility->scopeFor($workspace, $filters['user']), $this->narrowing($filters), $filters['from'], $filters['to']);
    }

    /** The report's filters as CrmOpportunityRepository::narrowed() reads them. */
    protected function narrowing(array $filters): array
    {
        return array_filter([
            'location_id'   => $filters['location_id'] ?? null,
            'sales_team_id' => $filters['sales_team_id'] ?? null,
            'owner_id'      => $filters['owner_id'] ?? null,
            'source_id'     => $filters['lead_source_id'] ?? null,
            'pipeline_id'   => $filters['pipeline_id'] ?? null,
            'stage_id'      => $filters['stage_id'] ?? null,
        ]);
    }

    /**
     * $rows' whole or weighted value in the base currency, from $values
     * (OpportunityForecast::baseValues()), with how many have none.
     *
     * @return array{total: string, unrated: int}
     */
    protected function sum(Collection $rows, array $values, string $kind = 'value'): array
    {
        $amounts = $rows->map(fn (CrmOpportunity $row) => $values[$row->id][$kind] ?? null);

        return ['total' => Decimal::sum($amounts->filter(fn ($amount) => $amount !== null)->all()), 'unrated' => $amounts->filter(fn ($amount) => $amount === null)->count()];
    }

    /**
     * $rows by $by - month (of $date's day), owner, sales team, lead source or
     * stage - each group with its name: every month of the range when by
     * month, a quiet one too; every open stage shown (openStages()) when by
     * stage; otherwise the most first, those without in a group of their own.
     *
     * @param  callable(CrmOpportunity): ?string  $date
     * @return list<array{label: string, rows: Collection<int, CrmOpportunity>}>
     */
    protected function grouped(Workspace $workspace, Collection $rows, string $by, array $filters, ?callable $date = null): array
    {
        $month  = fn (string $key) => $this->period->label($key, 'month', $filters['date_format'] ?? null);
        $groups = match ($by) {
            'month' => collect($this->period->buckets($filters['from'], $filters['to'], 'month'))->mapWithKeys(fn (string $key) => [$key => ['label' => $month($key), 'rows' => collect()]])->all(),
            'stage' => collect($this->openStages($workspace, $filters))->mapWithKeys(fn (array $stage) => ["stage:{$stage['id']}" => ['label' => $stage['label'], 'rows' => collect()]])->all(),
            default => [],
        };
        $several = $by === 'stage' && $this->severalPipelines($workspace, $filters);

        foreach ($rows as $row) {
            $key = match ($by) {
                'month'  => $this->period->bucket($date($row), 'month'),
                'owner'  => "owner:{$row->owner_id}",
                'team'   => "team:{$row->sales_team_id}",
                'source' => "source:{$row->source_id}",
                default  => "stage:{$row->stage_id}",
            };

            $groups[$key] ??= ['label' => match ($by) {
                'month'  => $month($key),
                'owner'  => $row->owner?->name ?? 'No owner',
                'team'   => $row->team?->name ?? 'No sales team',
                'source' => $row->source?->name ?? 'No source',
                default  => $this->stageName($row->stage, $several),
            }, 'rows' => collect()];

            $groups[$key]['rows']->push($row);
        }

        $groups = array_values($groups);

        if (! in_array($by, ['month', 'stage'], true)) {
            usort($groups, fn (array $a, array $b) => $b['rows']->count() <=> $a['rows']->count() ?: strcmp($a['label'], $b['label']));
        }

        return $groups;
    }

    /**
     * The open stages switched on of the pipelines switched on - the chosen
     * pipeline's only - in the pipelines' order, each named with its
     * pipeline while several show.
     *
     * @return list<array{id: int, label: string, stale_after_days: ?int}>
     */
    protected function openStages(Workspace $workspace, array $filters): array
    {
        $pipelines = $this->shownPipelines($workspace, $filters);
        $several   = $pipelines->count() > 1;

        return $pipelines->flatMap(fn (CrmPipeline $pipeline) => $pipeline->stages
            ->filter(fn (CrmStage $stage) => $stage->category === CrmStage::OPEN && $stage->is_active)
            ->map(fn (CrmStage $stage) => ['id' => $stage->id, 'label' => $several ? "{$pipeline->name} · {$stage->name}" : $stage->name, 'stale_after_days' => $stage->stale_after_days]))
            ->values()
            ->all();
    }

    /** Whether more than one pipeline shows, so a stage is named with its pipeline. */
    protected function severalPipelines(Workspace $workspace, array $filters): bool
    {
        return $this->shownPipelines($workspace, $filters)->count() > 1;
    }

    /** A stage's name - with its pipeline's when $several. */
    protected function stageName(?CrmStage $stage, bool $several): string
    {
        return $stage ? ($several && $stage->pipeline ? "{$stage->pipeline->name} · {$stage->name}" : $stage->name) : 'No stage';
    }

    /** The average full days from each of $moments to $until, to one place - none of none. */
    protected function averageDays(Collection $moments, CarbonInterface $until): ?string
    {
        $seconds = $moments->filter()->map(fn (CarbonInterface $moment) => max(0, $until->getTimestamp() - $moment->getTimestamp()));

        return $seconds->isEmpty() ? null : Decimal::round(bcdiv((string) $seconds->sum(), (string) ($seconds->count() * 86400), 4), 1);
    }

    /** How many were left out of the values for want of a rate today - none, no note. */
    protected function unratedNote(int $unrated): ?string
    {
        return $unrated === 0 ? null : ($unrated === 1
            ? '1 opportunity is in a currency without a rate today, so its value is left out.'
            : "{$unrated} opportunities are in currencies without a rate today, so their values are left out.");
    }

    /** "The open opportunities you see now" or "The opportunities won or lost from 01 Nov 2026 to 30 Nov 2026 that you see". */
    protected function counting(array $filters, bool $closed): string
    {
        return $closed
            ? 'The opportunities won or lost from '.$this->day($filters['from'], $filters).' to '.$this->day($filters['to'], $filters).' that you see, archived ones left out'
            : 'The open opportunities you see now, archived ones left out';
    }

    /** The pipelines switched on - the chosen one only - in their order, each with its stages: read once a report. */
    private function shownPipelines(Workspace $workspace, array $filters): Collection
    {
        $this->pipelinesRead ??= $this->pipelines->getFor($workspace);

        return $this->pipelinesRead
            ->filter(fn (CrmPipeline $pipeline) => ($filters['pipeline_id'] ?? null) ? $pipeline->id === (int) $filters['pipeline_id'] : $pipeline->is_active)
            ->values();
    }
}
