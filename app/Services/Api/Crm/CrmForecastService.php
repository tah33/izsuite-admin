<?php

namespace App\Services\Api\Crm;

use App\Models\User\CrmOpportunity;
use App\Models\User\User;
use App\Models\User\Workspace;
use App\Repositories\User\CrmOpportunityRepository;
use App\Services\Crm\OpportunityForecast;
use App\Services\Crm\OpportunityVisibility;
use App\Services\Shared\BusinessCalendar;
use App\Support\Decimal;
use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

/**
 * The Forecast (Doc 17 sections 17.3 and 17.14 - Phase G2): the open
 * opportunities a user sees - how many, their expected value, and their
 * weighted value (expected value × probability) - by expected closing month
 * (those already past under Overdue, this month and the next 11, then
 * Later), or by owner, sales team or stage; with what was won this month
 * beside it.
 *
 * Values are in the base currency at today's rates - an estimate, those of a
 * currency without a rate today left out and counted - or in the currency
 * chosen, among those in it. It is no cash-flow or financial forecast.
 */
class CrmForecastService
{
    public const GROUPS = ['month', 'owner', 'team', 'stage'];

    /** What narrows it: the Overview's filters, but the period. */
    private const FILTERS = ['location_id', 'sales_team_id', 'owner_id', 'source_id', 'pipeline_id', 'stage_id', 'currency'];

    private const MONTHS = 12;

    public function __construct(
        protected CrmOpportunityRepository $opportunities,
        protected OpportunityVisibility $visibility,
        protected OpportunityForecast $forecast,
        protected BusinessCalendar $calendar,
    ) {}

    /** $input is validated (CrmForecastRequest): group_by, and the filters. */
    public function rows(Workspace $workspace, User $user, array $input): array
    {
        $groupBy  = $input['group_by'] ?? 'month';
        $filters  = array_filter(Arr::only($input, self::FILTERS));
        $currency = $filters['currency'] ?? null;
        $today    = $this->calendar->today($workspace);

        $rows = $this->opportunities->forForecast($workspace, $this->visibility->scopeFor($workspace, $user), $filters, $today->startOfMonth()->toDateString(), $today->endOfMonth()->toDateString());
        $open = $rows->where('status', CrmOpportunity::OPEN);
        $won  = $rows->where('status', CrmOpportunity::WON);

        $groups = match ($groupBy) {
            'owner' => $this->byKey($rows, fn (CrmOpportunity $row) => $row->owner_id, fn (CrmOpportunity $row) => $row->owner?->name ?? 'No owner'),
            'team'  => $this->byKey($rows, fn (CrmOpportunity $row) => $row->sales_team_id ?? 0, fn (CrmOpportunity $row) => $row->team?->name ?? 'No team'),
            'stage' => $this->byStage($rows),
            default => $this->byMonth($today, $open, $won),
        };

        return [
            'currency' => $currency ?? $workspace->base_currency,
            'group_by' => $groupBy,
            'rows'     => array_map(fn (array $group) => ['key' => $group['key'], 'label' => $group['label'], ...$this->figures($workspace, $group['open'], $group['won'], $currency)], $groups),
            'total'    => $this->figures($workspace, $open, $won, $currency),
        ];
    }

    /**
     * Overdue - open, its expected closing day passed - this month from
     * today, the next 11 months, then Later. Each month shows, empty or not;
     * Overdue and Later only holding something. What was won this month is
     * this month's.
     *
     * @return list<array{key: string, label: string, open: Collection, won: Collection}>
     */
    private function byMonth(CarbonImmutable $today, Collection $open, Collection $won): array
    {
        $day    = fn (CrmOpportunity $row) => $row->expected_close_date?->toDateString() ?? '';
        $last   = $today->startOfMonth()->addMonths(self::MONTHS - 1)->endOfMonth()->toDateString();
        $groups = [['key' => 'overdue', 'label' => 'Overdue', 'open' => $open->filter(fn ($row) => $day($row) < $today->toDateString()), 'won' => collect()]];

        for ($month = 0; $month < self::MONTHS; $month++) {
            $start = $today->startOfMonth()->addMonths($month);
            $from  = $month === 0 ? $today->toDateString() : $start->toDateString();
            $to    = $start->endOfMonth()->toDateString();

            $groups[] = [
                'key'   => $start->format('Y-m'),
                'label' => $start->format('F Y'),
                'open'  => $open->filter(fn ($row) => $day($row) >= $from && $day($row) <= $to),
                'won'   => $month === 0 ? $won : collect(),
            ];
        }

        $groups[] = ['key' => 'later', 'label' => 'Later', 'open' => $open->filter(fn ($row) => $day($row) > $last), 'won' => collect()];

        return array_values(array_filter($groups, fn (array $group) => ! in_array($group['key'], ['overdue', 'later'], true) || $group['open']->isNotEmpty()));
    }

    /**
     * One group per value of $key among $rows, by its label: its open ones
     * and those won this month.
     *
     * @return list<array{key: string, label: string, open: Collection, won: Collection}>
     */
    private function byKey(Collection $rows, callable $key, callable $label): array
    {
        // Numeric keys come back as integers: each is made a string again.
        return $rows->groupBy(fn (CrmOpportunity $row) => (string) $key($row))
            ->map(fn (Collection $group, $id) => [
                'key'   => (string) $id,
                'label' => $label($group->first()),
                'open'  => $group->where('status', CrmOpportunity::OPEN),
                'won'   => $group->where('status', CrmOpportunity::WON),
            ])
            ->sortBy(fn (array $group) => [$group['key'] === '0' ? 1 : 0, mb_strtolower($group['label'])])
            ->values()
            ->all();
    }

    /**
     * One group per stage, in its pipeline's order - named with its pipeline
     * while they come from several. A won stage holds what was won this month.
     *
     * @return list<array{key: string, label: string, open: Collection, won: Collection}>
     */
    private function byStage(Collection $rows): array
    {
        $several = $rows->pluck('stage.crm_pipeline_id')->unique()->count() > 1;

        return $rows->groupBy('stage_id')
            ->map(function (Collection $group, $id) use ($several) {
                $stage = $group->first()->stage;

                return [
                    'key'   => (string) $id,
                    'label' => $several ? "{$stage?->pipeline?->name} · {$stage?->name}" : (string) $stage?->name,
                    'order' => [$stage?->pipeline?->position ?? 0, $stage?->crm_pipeline_id ?? 0, $stage?->position ?? 0],
                    'open'  => $group->where('status', CrmOpportunity::OPEN),
                    'won'   => $group->where('status', CrmOpportunity::WON),
                ];
            })
            ->sortBy('order')
            ->map(fn (array $group) => Arr::except($group, 'order'))
            ->values()
            ->all();
    }

    /**
     * How many open, their expected and weighted value, and what was won -
     * each value with how many were left out for want of a rate today.
     */
    private function figures(Workspace $workspace, Collection $open, Collection $won, ?string $currency): array
    {
        return [
            'count'    => $open->count(),
            'value'    => $this->value($workspace, $open, false, $currency),
            'weighted' => $this->value($workspace, $open, true, $currency),
            'won'      => $this->value($workspace, $won, false, $currency),
        ];
    }

    /** As the Overview values them (CrmOverviewService::value). @return array{total: string, unrated: int} */
    private function value(Workspace $workspace, Collection $rows, bool $weighted, ?string $currency): array
    {
        if ($currency === null) {
            return $this->forecast->total($workspace, $rows, $weighted);
        }

        return ['total' => Decimal::sum($rows->map(fn (CrmOpportunity $row) => $weighted
            ? Decimal::round(bcdiv(bcmul((string) $row->expected_value, (string) $row->probability, 4), '100', 4))
            : Decimal::round((string) $row->expected_value))), 'unrated' => 0];
    }
}
