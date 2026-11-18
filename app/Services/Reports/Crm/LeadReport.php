<?php

namespace App\Services\Reports\Crm;

use App\Models\User\CrmLead;
use App\Models\User\Workspace;
use App\Repositories\User\CrmLeadRepository;
use App\Repositories\User\CrmSettingRepository;
use App\Repositories\User\ExpenseReportRepository;
use App\Repositories\User\PurchaseReportRepository;
use App\Repositories\User\SalesReportRepository;
use App\Services\Crm\LeadVisibility;
use App\Services\Reports\Report;
use App\Services\Reports\ReportPeriod;
use App\Services\Reports\SalesFigures;
use App\Services\Shared\BusinessCalendar;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * CRM's lead reports (Doc 17 section 17.29 - Phase H1), each built from the
 * leads the user sees (LeadVisibility) that were added in the range - its
 * days the business's - narrowed by location, sales team, owner and lead
 * source, and grouped by month, source, owner, sales team or status. Leads
 * are not sales (17.31): a converted lead becomes an opportunity, and only
 * issued invoices are sales.
 */
abstract class LeadReport extends Report
{
    /** How the lead reports with a Group By can group their leads, by month unless told. */
    public const GROUPS = ['month' => 'Month', 'source' => 'Lead Source', 'owner' => 'Owner', 'team' => 'Sales Team'];

    /** The statuses as the reports name them, in the order a lead goes through them. */
    protected const STATUSES = [
        CrmLead::NEW         => 'New',
        CrmLead::ASSIGNED    => 'Assigned',
        CrmLead::CONTACTED   => 'Contacted',
        CrmLead::QUALIFIED   => 'Qualified',
        CrmLead::UNQUALIFIED => 'Unqualified',
        CrmLead::CONVERTED   => 'Converted',
        CrmLead::ARCHIVED    => 'Archived',
    ];

    /** Still being worked, not qualified yet. */
    protected const WORKING = [CrmLead::NEW, CrmLead::ASSIGNED, CrmLead::CONTACTED];

    /** Qualified: qualified now, or converted since. */
    protected const QUALIFIED = [CrmLead::QUALIFIED, CrmLead::CONVERTED];

    /** The note every lead report ends with. */
    protected const NOT_SALES = 'Leads are not sales: a converted lead becomes an opportunity, and only issued invoices count as sales.';

    public function __construct(
        SalesReportRepository $sales,
        SalesFigures $figures,
        ReportPeriod $period,
        PurchaseReportRepository $purchases,
        ExpenseReportRepository $expenses,
        protected CrmLeadRepository $leads,
        protected CrmSettingRepository $settings,
        protected LeadVisibility $visibility,
        protected BusinessCalendar $calendar,
    ) {
        parent::__construct($sales, $figures, $period, $purchases, $expenses);
    }

    /** The leads the user sees added from the range's first day to its last, narrowed by the report's filters. */
    protected function added(Workspace $workspace, array $filters): Collection
    {
        $zone = $this->calendar->timezone($workspace);

        return $this->leads->forReports(
            $workspace,
            $this->visibility->scopeFor($workspace, $filters['user']),
            array_filter([
                'location_id'   => $filters['location_id'] ?? null,
                'sales_team_id' => $filters['sales_team_id'] ?? null,
                'owner_id'      => $filters['owner_id'] ?? null,
                'source_id'     => $filters['lead_source_id'] ?? null,
            ]),
            CarbonImmutable::parse($filters['from'], $zone)->startOfDay()->utc(),
            CarbonImmutable::parse($filters['to'], $zone)->endOfDay()->utc(),
        );
    }

    /**
     * $leads by $by - month, source, owner, sales team or status - each group
     * with its name: every month of the range when by month, a quiet one too,
     * and every status in order when by status; otherwise the most leads
     * first, those without a source, owner or team in a group of their own.
     *
     * @return list<array{label: string, leads: Collection<int, CrmLead>}>
     */
    protected function grouped(Workspace $workspace, Collection $leads, string $by, array $filters): array
    {
        $zone   = $this->calendar->timezone($workspace);
        $month  = fn (string $key) => $this->period->label($key, 'month', $filters['date_format'] ?? null);
        $groups = match ($by) {
            'month'  => collect($this->period->buckets($filters['from'], $filters['to'], 'month'))->mapWithKeys(fn (string $key) => [$key => ['label' => $month($key), 'leads' => collect()]])->all(),
            'status' => array_map(fn (string $label) => ['label' => $label, 'leads' => collect()], self::STATUSES),
            default  => [],
        };

        foreach ($leads as $lead) {
            $key = match ($by) {
                'month'  => $this->period->bucket($lead->created_at->copy()->setTimezone($zone)->toDateString(), 'month'),
                'status' => $lead->status,
                'source' => "source:{$lead->source_id}",
                'owner'  => "owner:{$lead->owner_id}",
                default  => "team:{$lead->sales_team_id}",
            };

            $groups[$key] ??= ['label' => match ($by) {
                'month'  => $month($key),
                'status' => self::STATUSES[$lead->status] ?? ucfirst($lead->status),
                'source' => $lead->source?->name ?? 'No source',
                'owner'  => $lead->owner?->name ?? 'No owner',
                default  => $lead->team?->name ?? 'No sales team',
            }, 'leads' => collect()];

            $groups[$key]['leads']->push($lead);
        }

        $groups = array_values($groups);

        if (! in_array($by, ['month', 'status'], true)) {
            usort($groups, fn (array $a, array $b) => $b['leads']->count() <=> $a['leads']->count() ?: strcmp($a['label'], $b['label']));
        }

        return $groups;
    }

    /** How many of $leads stand at one of $statuses. */
    protected function counted(Collection $leads, array $statuses): int
    {
        return $leads->whereIn('status', $statuses)->count();
    }

    /** $part of $whole as a percentage to one place - none of none. */
    protected function share(int $part, int $whole): ?string
    {
        return $whole > 0 ? $this->percent((string) $part, (string) $whole) : null;
    }

    /** "The leads added from 01 Nov 2026 to 30 Nov 2026 that you see" - how each lead report's first note starts. */
    protected function counting(array $filters): string
    {
        return 'The leads added from '.$this->day($filters['from'], $filters).' to '.$this->day($filters['to'], $filters).' that you see';
    }
}
