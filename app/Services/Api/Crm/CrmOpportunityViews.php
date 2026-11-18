<?php

namespace App\Services\Api\Crm;

use App\Models\User\CrmOpportunity;
use App\Models\User\CrmPipeline;
use App\Models\User\CrmStage;
use App\Models\User\User;
use App\Models\User\Workspace;
use App\Repositories\User\CrmOpportunityRepository;
use App\Repositories\User\CrmPipelineRepository;
use App\Services\Crm\OpportunityForecast;
use App\Services\Crm\OpportunityVisibility;
use App\Services\Crm\PipelineChoice;
use App\Services\Shared\BusinessCalendar;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * The Opportunities page's board and calendar (Doc 17 section 17.14 - Phase
 * D4), beside its list. Each shows only the opportunities the user sees, as
 * the list does (OpportunityVisibility), and no archived one.
 *
 * The board is one pipeline's: a column for each of its stages switched on -
 * and any other an opportunity is at - in its order. An open stage's column
 * holds the open opportunities there, the soonest to close first; a won or
 * lost stage's, those closed in the last CLOSED_DAYS days, the latest first.
 * Each column shows up to CARDS_PER_COLUMN of them, with how many it holds
 * and what they are worth in the base currency at today's rates: weighted by
 * probability while open, whole once won or lost.
 *
 * The calendar is a month of the list - its filters, its rules - each
 * opportunity on its expected closing day.
 */
class CrmOpportunityViews
{
    /** How many cards a column shows; the list has every one. */
    public const CARDS_PER_COLUMN = 50;

    /** How far back the Won and Lost columns reach, in days. */
    public const CLOSED_DAYS = 30;

    /** How many opportunities a month of the calendar shows at most. */
    public const CALENDAR_LIMIT = 500;

    public function __construct(
        protected CrmOpportunityRepository $opportunities,
        protected CrmPipelineRepository $pipelines,
        protected CrmOpportunityService $service,
        protected OpportunityVisibility $visibility,
        protected OpportunityForecast $forecast,
        protected PipelineChoice $pipelineChoice,
        protected BusinessCalendar $calendar,
    ) {}

    /**
     * The board of $filters['pipeline_id']'s pipeline - the first switched on
     * when none is asked for - searched and by owner as the list is. Every
     * pipeline of the business is offered, a switched-off one still holding
     * what is in it.
     *
     * @return array{pipeline: CrmPipeline, pipelines: Collection<int, CrmPipeline>, columns: list<array{stage: CrmStage, count: int, total: string, unrated: int, cards: list<CrmOpportunity>}>}
     */
    public function board(Workspace $workspace, User $user, array $filters): array
    {
        $first     = $this->pipelineChoice->active($workspace)->first();
        $pipelines = $this->pipelines->getFor($workspace);
        $pipeline  = isset($filters['pipeline_id']) ? $pipelines->firstWhere('id', (int) $filters['pipeline_id']) : $pipelines->firstWhere('id', $first?->id);
        $pipeline ?? abort(404, 'Pipeline not found.');

        $since = $this->calendar->today($workspace)->subDays(self::CLOSED_DAYS)->toDateString();
        $held  = $this->opportunities->onBoard($workspace, $this->service->filters($workspace, $user, $filters), $this->visibility->scopeFor($workspace, $user), $pipeline->id, $since)
            ->groupBy('stage_id');

        $columns = $pipeline->stages
            ->filter(fn (CrmStage $stage) => $stage->is_active || $held->has($stage->id))
            ->map(fn (CrmStage $stage) => $this->column($workspace, $stage, $held->get($stage->id, collect())))
            ->values();

        // Every column's cards in one read, each marked as the list marks it.
        $cards = $this->opportunities->cards($workspace, $columns->flatMap(fn (array $column) => $column['ids'])->all())->keyBy('id');
        $this->service->mark($workspace, $cards->all());

        return [
            'pipeline'  => $pipeline,
            'pipelines' => $pipelines,
            'columns'   => $columns->map(fn (array $column) => [
                'stage'   => $column['stage'],
                'count'   => $column['count'],
                'total'   => $column['total'],
                'unrated' => $column['unrated'],
                'cards'   => array_values(array_filter(array_map(fn (int $id) => $cards->get($id), $column['ids']))),
            ])->all(),
        ];
    }

    /**
     * A month of the calendar - $filters['month'], or the business's this
     * month - with the list's other filters: its opportunities by expected
     * closing day, at most CALENDAR_LIMIT of them, how many there are, the
     * business's today and the day its week starts (0 Sunday to 6 Saturday).
     *
     * @return array{month: string, today: string, week_start: int, count: int, entries: Collection<int, CrmOpportunity>}
     */
    public function calendar(Workspace $workspace, User $user, array $filters): array
    {
        $today = $this->calendar->today($workspace);
        $month = isset($filters['month']) ? Carbon::createFromFormat('!Y-m', $filters['month']) : Carbon::parse($today->format('Y-m-01'));
        unset($filters['month']);

        $found = $this->opportunities->closingBetween(
            $workspace,
            $this->service->filters($workspace, $user, $filters),
            $this->visibility->scopeFor($workspace, $user),
            $month->copy()->startOfMonth()->toDateString(),
            $month->copy()->endOfMonth()->toDateString(),
            self::CALENDAR_LIMIT,
        );

        $this->service->mark($workspace, $found['rows']->all());

        return [
            'month'      => $month->format('Y-m'),
            'today'      => $today->toDateString(),
            'week_start' => $workspace->weekStartDay(),
            'count'      => $found['count'],
            'entries'    => $found['rows'],
        ];
    }

    /**
     * $stage's column from the opportunities $held there: the ids of those
     * it shows, in its order, how many there are and what they are worth.
     *
     * @param  Collection<int, CrmOpportunity>  $held
     * @return array{stage: CrmStage, ids: list<int>, count: int, total: string, unrated: int}
     */
    private function column(Workspace $workspace, CrmStage $stage, Collection $held): array
    {
        $open   = $stage->category === CrmStage::OPEN;
        $sorted = $open
            ? $held->sortBy([fn (CrmOpportunity $a, CrmOpportunity $b) => [$a->expected_close_date, $a->id] <=> [$b->expected_close_date, $b->id]])
            : $held->sortBy([fn (CrmOpportunity $a, CrmOpportunity $b) => [$b->closed_on, $b->id] <=> [$a->closed_on, $a->id]]);
        $worth  = $this->forecast->total($workspace, $held, $open);

        return [
            'stage'   => $stage,
            'ids'     => $sorted->take(self::CARDS_PER_COLUMN)->pluck('id')->map(fn ($id) => (int) $id)->values()->all(),
            'count'   => $held->count(),
            'total'   => $worth['total'],
            'unrated' => $worth['unrated'],
        ];
    }
}
