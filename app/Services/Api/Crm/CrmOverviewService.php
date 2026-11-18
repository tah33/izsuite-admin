<?php

namespace App\Services\Api\Crm;

use App\Models\User\CrmOpportunity;
use App\Models\User\User;
use App\Models\User\Workspace;
use App\Repositories\User\CrmActivityRepository;
use App\Repositories\User\CrmLeadRepository;
use App\Repositories\User\CrmOpportunityRepository;
use App\Repositories\User\CrmSettingRepository;
use App\Services\Crm\ActivityVisibility;
use App\Services\Crm\FollowUpCutoffs;
use App\Services\Crm\LeadVisibility;
use App\Services\Crm\OpportunityForecast;
use App\Services\Crm\OpportunityVisibility;
use App\Services\Reports\ReportPeriod;
use App\Services\Shared\BusinessCalendar;
use App\Services\Shared\WorkspaceAccess;
use App\Support\Decimal;
use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

/**
 * The CRM Overview (Doc 17 sections 17.3 and 17.4 - Phase G1): new leads,
 * leads awaiting follow-up and qualified ones and the conversion rate; open
 * opportunities with the pipeline value and weighted forecast, those closing
 * soon, and those won and lost; and overdue activities - each counting only
 * what the user sees. Phase G3b adds the follow-up warnings (section 17.23):
 * leads in the queue and leads not contacted in time; open opportunities
 * with no activity planned, stale ones, and those closing soon without
 * recent activity (FollowUpCutoffs).
 *
 * The period counts the new leads, the conversion rate and the won and lost;
 * the rest are as they stand now. Values are in the base currency at
 * today's rates, an estimate - those of a currency without a rate today are
 * left out and counted - or in the currency chosen, among those in it. None
 * of it is sales.
 */
class CrmOverviewService
{
    /** What narrows the figures, besides the period. */
    private const FILTERS = ['location_id', 'sales_team_id', 'owner_id', 'source_id', 'pipeline_id', 'stage_id', 'currency'];

    public function __construct(
        protected CrmLeadRepository $leads,
        protected CrmOpportunityRepository $opportunities,
        protected CrmActivityRepository $activities,
        protected CrmSettingRepository $settings,
        protected LeadVisibility $leadVisibility,
        protected OpportunityVisibility $opportunityVisibility,
        protected ActivityVisibility $activityVisibility,
        protected OpportunityForecast $forecast,
        protected WorkspaceAccess $access,
        protected ReportPeriod $period,
        protected BusinessCalendar $calendar,
        protected FollowUpCutoffs $cutoffs,
    ) {}

    /** $input is validated (CrmOverviewRequest): range or date_from and date_to, and the filters. */
    public function figures(Workspace $workspace, User $user, array $input): array
    {
        [$from, $to] = $this->period->range($workspace, $input['range'] ?? null, $input['date_from'] ?? null, $input['date_to'] ?? null);
        $settings    = $this->settings->forWorkspace($workspace);
        $filters     = array_filter(Arr::only($input, self::FILTERS));
        $currency    = $filters['currency'] ?? null;
        $today       = $this->calendar->today($workspace);

        $rows   = $this->opportunities->forOverview($workspace, $this->opportunityVisibility->scopeFor($workspace, $user), $filters, $from, $to);
        $open   = $rows->where('status', CrmOpportunity::OPEN);
        $soonBy = $today->addDays($settings->closing_soon_days)->toDateString();
        $soon   = $open->filter(fn (CrmOpportunity $row) => $row->expected_close_date !== null
            && $row->expected_close_date->toDateString() >= $today->toDateString()
            && $row->expected_close_date->toDateString() <= $soonBy);

        $value = fn (Collection $rows, bool $weighted = false) => $this->value($workspace, $rows, $weighted, $currency);

        return [
            'from'              => $from,
            'to'                => $to,
            'currency'          => $currency ?? $workspace->base_currency,
            'closing_soon_days' => $settings->closing_soon_days,
            // Phase G3b: the follow-up warnings' periods.
            'lead_response_days'   => $settings->lead_response_days,
            'recent_activity_days' => $settings->recent_activity_days,
            'leads'             => $this->leadFigures($workspace, $user, $settings->usesLeads(), $filters, $from, $to),
            'open'              => ['count' => $open->count(), 'value' => $value($open), 'weighted' => $value($open, true)],
            'closing_soon'      => ['count' => $soon->count(), 'value' => $value($soon)],
            'won'               => ['count' => $rows->where('status', CrmOpportunity::WON)->count(), 'value' => $value($rows->where('status', CrmOpportunity::WON))],
            'lost'              => ['count' => $rows->where('status', CrmOpportunity::LOST)->count(), 'value' => $value($rows->where('status', CrmOpportunity::LOST))],
            'overdue_activities' => $this->activities->overdueCount($workspace, $this->activityVisibility->scopeFor($workspace, $user), $filters, now()),
            // Phase G3b: the open opportunities with no activity planned, stale, and closing soon without recent activity.
            'follow_up'         => $this->opportunities->followUpCounts(
                $workspace,
                $this->opportunityVisibility->scopeFor($workspace, $user),
                $filters,
                $this->cutoffs->staleSince($workspace),
                $this->cutoffs->quiet($workspace),
            ),
        ];
    }

    /**
     * The leads' figures - null while the business works with opportunities
     * only, or for someone who may not view leads. The period's days are the
     * business's, so its bounds are moments in the business's time zone.
     * Phase G3b adds the warnings: those in the queue, and those not
     * contacted in time.
     */
    private function leadFigures(Workspace $workspace, User $user, bool $usesLeads, array $filters, string $from, string $to): ?array
    {
        if (! $usesLeads || ! $this->access->allows($user, $workspace, 'crm.leads.view')) {
            return null;
        }

        $zone   = $this->calendar->timezone($workspace);
        $scope  = $this->leadVisibility->scopeFor($workspace, $user);
        $counts = $this->leads->overview(
            $workspace,
            $scope,
            $filters,
            CarbonImmutable::parse($from, $zone)->startOfDay()->utc(),
            CarbonImmutable::parse($to, $zone)->endOfDay()->utc(),
        );
        $waiting = $this->leads->followUpCounts($workspace, $scope, $filters, $this->cutoffs->uncontactedBefore($workspace));

        return [
            'new'             => $counts['new'],
            'awaiting'        => $counts['awaiting'],
            'qualified'       => $counts['qualified'],
            'converted'       => $counts['converted'],
            // Of the leads added in the period, the share converted so far; none added, no rate.
            'conversion_rate' => $counts['new'] > 0 ? round($counts['converted'] * 100 / $counts['new'], 1) : null,
            'unassigned'      => $waiting['unassigned'],
            'uncontacted'     => $waiting['uncontacted'],
        ];
    }

    /**
     * $rows' whole or weighted value: in the currency chosen, all of them in
     * it; else in the base currency at today's rates, those without a rate
     * today left out and counted.
     *
     * @return array{total: string, unrated: int}
     */
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
