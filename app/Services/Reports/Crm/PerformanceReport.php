<?php

namespace App\Services\Reports\Crm;

use App\Models\User\CrmActivity;
use App\Models\User\CrmLead;
use App\Models\User\CrmOpportunity;
use App\Models\User\Workspace;
use App\Repositories\User\CrmActivityRepository;
use App\Repositories\User\CrmLeadRepository;
use App\Repositories\User\CrmOpportunityRepository;
use App\Repositories\User\CrmPipelineRepository;
use App\Repositories\User\CrmSettingRepository;
use App\Repositories\User\ExpenseReportRepository;
use App\Repositories\User\PurchaseReportRepository;
use App\Repositories\User\SalesReportRepository;
use App\Services\Crm\ActivityVisibility;
use App\Services\Crm\LeadVisibility;
use App\Services\Crm\OpportunityForecast;
use App\Services\Crm\OpportunityVisibility;
use App\Services\Reports\ReportPeriod;
use App\Services\Reports\ReportTable;
use App\Services\Reports\SalesFigures;
use App\Services\Shared\BusinessCalendar;
use App\Services\Shared\WorkspaceAccess;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Salesperson Performance and Sales Team Performance (Doc 17 sections 17.24
 * and 17.29 - Phase H3), the grouping each is built with fixed (ReportCatalog
 * ::GROUPED). For each owner - a lead's or an opportunity's, whom an
 * activity is given to - or each sales team - a lead's or an opportunity's,
 * an activity's its record's - over the range: the leads added and how many
 * of them converted (while the business uses leads, for whoever may view
 * them), the opportunities won and lost with the win rate and the value won,
 * the open pipeline now, and the activities done (for whoever may view
 * activities). Each counts only what the user sees; values are in the base
 * currency at today's rates. Commission is not worked out (17.24).
 */
class PerformanceReport extends OpportunityReport
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
        protected CrmLeadRepository $leads,
        protected LeadVisibility $leadVisibility,
        protected CrmSettingRepository $settings,
        protected CrmActivityRepository $activities,
        protected ActivityVisibility $activityVisibility,
        protected WorkspaceAccess $access,
    ) {
        parent::__construct($sales, $figures, $period, $purchases, $expenses, $opportunities, $pipelines, $visibility, $forecast, $calendar);
    }

    public function build(Workspace $workspace, array $filters): ReportTable
    {
        $team  = $filters['group_by'] === 'team';
        $user  = $filters['user'];
        $base  = $workspace->base_currency;
        $zone  = $this->calendar->timezone($workspace);
        $from  = CarbonImmutable::parse($filters['from'], $zone)->startOfDay()->utc();
        $to    = CarbonImmutable::parse($filters['to'], $zone)->endOfDay()->utc();
        $where = array_filter(['location_id' => $filters['location_id'] ?? null, 'sales_team_id' => $filters['sales_team_id'] ?? null]);

        $withLeads      = $this->settings->forWorkspace($workspace)->usesLeads() && $this->access->allows($user, $workspace, 'crm.leads.view');
        $withActivities = $this->access->allows($user, $workspace, 'crm.activities.view');

        $scope  = $this->visibility->scopeFor($workspace, $user);
        $closed = $this->opportunities->forReports($workspace, $scope, $where, $filters['from'], $filters['to']);
        $open   = $this->opportunities->forReports($workspace, $scope, $where);
        $leads  = $withLeads ? $this->leads->forReports($workspace, $this->leadVisibility->scopeFor($workspace, $user), $where, $from, $to) : collect();
        $teamOf = fn (CrmActivity $activity) => $activity->opportunity?->sales_team_id ?? $activity->lead?->sales_team_id;
        $done   = $withActivities
            ? $this->activities->completedBetween($workspace, $this->activityVisibility->scopeFor($workspace, $user), array_filter(['location_id' => $filters['location_id'] ?? null]), $from, $to)
                ->filter(fn (CrmActivity $activity) => ! isset($where['sales_team_id']) || (int) $teamOf($activity) === (int) $where['sales_team_id'])
            : collect();
        $values = $this->forecast->baseValues($workspace, $closed->concat($open));

        $groups = [];
        $none   = $team ? 'No sales team' : 'No owner';
        $add    = function (string $part, $record, ?int $id, ?string $label) use (&$groups, $none) {
            $key = (string) ($id ?? 0);

            $groups[$key] ??= ['label' => $label ?? $none, 'leads' => collect(), 'closed' => collect(), 'open' => collect(), 'done' => collect()];
            $groups[$key][$part]->push($record);
        };

        foreach ($leads as $lead) {
            $add('leads', $lead, $team ? $lead->sales_team_id : $lead->owner_id, $team ? $lead->team?->name : $lead->owner?->name);
        }

        foreach (['closed' => $closed, 'open' => $open] as $part => $rows) {
            foreach ($rows as $row) {
                $add($part, $row, $team ? $row->sales_team_id : $row->owner_id, $team ? $row->team?->name : $row->owner?->name);
            }
        }

        foreach ($done as $activity) {
            $add('done', $activity, $team ? $teamOf($activity) : $activity->assigned_to, $team ? ($activity->opportunity?->team?->name ?? $activity->lead?->team?->name) : $activity->assignee?->name);
        }

        $figures = function (array $group) use ($values, $withLeads, $withActivities) {
            $won = $group['closed']->where('status', CrmOpportunity::WON);

            return [
                ...($withLeads ? ['leads' => $group['leads']->count(), 'converted' => $group['leads']->where('status', CrmLead::CONVERTED)->count()] : []),
                'won'       => $won->count(),
                'lost'      => $group['closed']->count() - $won->count(),
                'rate'      => $this->share($won->count(), $group['closed']->count()),
                'won_value' => $this->sum($won, $values)['total'],
                'pipeline'  => $this->sum($group['open'], $values)['total'],
                ...($withActivities ? ['done' => $group['done']->count()] : []),
            ];
        };

        $rows = array_map(fn (array $group) => ['group' => $group['label'], ...$figures($group)], array_values($groups));
        usort($rows, fn (array $a, array $b) => bccomp($b['won_value'], $a['won_value'], 2) ?: strcmp($a['group'], $b['group']));

        return new ReportTable(
            title: $filters['report_name'] ?? ($team ? 'Sales Team Performance' : 'Salesperson Performance'),
            period: $this->between($filters),
            columns: [
                $this->column('group', $team ? 'Sales Team' : 'Salesperson'),
                ...($withLeads ? [$this->column('leads', 'Leads Added', 'number'), $this->column('converted', 'Converted', 'number')] : []),
                $this->column('won', 'Won', 'number'),
                $this->column('lost', 'Lost', 'number'),
                $this->column('rate', 'Win Rate', 'percent'),
                $this->column('won_value', 'Won Value', 'money', $base),
                $this->column('pipeline', 'Open Pipeline', 'money', $base),
                ...($withActivities ? [$this->column('done', 'Activities Done', 'number')] : []),
            ],
            rows: $rows,
            totals: ['group' => 'Total', ...$figures(['leads' => $leads, 'closed' => $closed, 'open' => $open, 'done' => $done])],
            notes: array_values(array_filter([
                'From '.$this->day($filters['from'], $filters).' to '.$this->day($filters['to'], $filters).': '
                    .($withLeads ? 'the leads added and how many of them converted, ' : '')
                    .'the opportunities won and lost, with the win rate and the value won'
                    .($withActivities ? ', and the activities done' : '')
                    .($team
                        ? ' - by the sales team each lead and opportunity names, an activity by its record\'s'
                        : ' - by whom each lead and opportunity is given to, an activity by whom it is given to')
                    .'. Open Pipeline is the open opportunities\' expected value now.',
                "The records you see, archived opportunities left out. Values are in {$base} at today's rates. Commission is not worked out here.",
                $this->unratedNote($this->sum($closed->concat($open), $values)['unrated']),
                self::NOT_SALES,
            ])),
        );
    }
}
