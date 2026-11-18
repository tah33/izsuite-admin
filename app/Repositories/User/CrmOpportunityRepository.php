<?php

namespace App\Repositories\User;

use App\Models\User\Contact;
use App\Models\User\CrmActivity;
use App\Models\User\CrmOpportunity;
use App\Models\User\CrmOpportunityItem;
use App\Models\User\CrmOption;
use App\Models\User\CrmStageVisit;
use App\Models\User\Location;
use App\Models\User\Workspace;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * A business's opportunities (Doc 17 Phases C1 and D1), each query of the
 * Opportunities page narrowed to those the signed-in user sees
 * (OpportunityVisibility's scope).
 */
class CrmOpportunityRepository
{
    private const SHOWN_WITH = [
        'contact:id,name,contact_number', 'owner:id,first_name,last_name', 'team:id,name', 'location:id,name', 'source:id,name',
        'stage:id,name,probability,category,stale_after_days,required_fields', 'pipeline.stages', 'lead:id,name', 'creator:id,first_name,last_name',
        'interestedItems', 'stageVisits.stage:id,name', 'stageVisits.mover:id,first_name,last_name',
        // How it ended, and who archived it (Phase D2a).
        'closer:id,first_name,last_name', 'lostReason:id,name', 'archiver:id,first_name,last_name',
    ];

    /** The Opportunities page: those $filters find (see filtered()), by expected closing date, soonest first. */
    public function paginateFor(Workspace $workspace, array $filters, array $scope, int $perPage): LengthAwarePaginator
    {
        return $this->filtered($workspace, $filters, $scope)
            ->with(['contact:id,name', 'pipeline:id,name', 'stage:id,name,stale_after_days', 'owner:id,first_name,last_name'])
            ->orderBy('expected_close_date')
            ->orderBy('id')
            ->paginate($perPage);
    }

    /**
     * The calendar (Phase D4): those $filters find closing from $from to
     * $to, by day - at most $limit of them - and how many there are.
     *
     * @return array{rows: Collection<int, CrmOpportunity>, count: int}
     */
    public function closingBetween(Workspace $workspace, array $filters, array $scope, string $from, string $to, int $limit): array
    {
        $query = $this->filtered($workspace, $filters, $scope)->whereBetween('expected_close_date', [$from, $to]);

        return [
            'count' => (clone $query)->count(),
            'rows'  => $query->with(['contact:id,name', 'stage:id,name'])->orderBy('expected_close_date')->orderBy('id')->limit($limit)->get(),
        ];
    }

    /**
     * The board (Phase D4): $pipelineId's open opportunities that $scope
     * sees and $filters find (search and owner, as the list's), and those won
     * or lost since $closedSince - none archived - each with only what its
     * column's order, count and total need.
     *
     * @return Collection<int, CrmOpportunity>
     */
    public function onBoard(Workspace $workspace, array $filters, array $scope, int $pipelineId, string $closedSince): Collection
    {
        return $this->filtered($workspace, ['search' => $filters['search'] ?? null, 'owner_id' => $filters['owner_id'] ?? null, 'status' => 'all'], $scope)
            ->where('pipeline_id', $pipelineId)
            ->where(fn (Builder $query) => $query->where('status', CrmOpportunity::OPEN)->orWhere('closed_on', '>=', $closedSince))
            ->get(['id', 'stage_id', 'status', 'currency', 'expected_value', 'probability', 'expected_close_date', 'closed_on']);
    }

    /**
     * The board's cards (Phase D4): those of $ids - found by onBoard(), so
     * ones the user sees - with what a card shows.
     *
     * @param  list<int>  $ids
     * @return Collection<int, CrmOpportunity>
     */
    public function cards(Workspace $workspace, array $ids): Collection
    {
        return CrmOpportunity::query()
            ->where('workspace_id', $workspace->id)
            ->whereIn('id', $ids)
            ->with(['contact:id,name', 'stage:id,name,stale_after_days', 'owner:id,first_name,last_name'])
            ->get();
    }

    /**
     * Those $scope sees that $filters find, not yet ordered - the list's,
     * the calendar's and the board's start: open ones, or those of one
     * status, or all of them, searched by their name or their contact's, by
     * stage, pipeline (Phase D3b) and owner. Archived ones only show with the
     * "archived" status (Phase D2a), whatever theirs. The follow-up statuses
     * are open ones too: "stale" (Phase D3a) those that entered their stage
     * before $filters['stale_since'] says for it ([stage id => that moment]);
     * Phase G3b's "no_activity" those with none planned, and "quiet" those
     * closing from $filters['closing_from'] to ['closing_to'] with no
     * activity since ['quiet_since'] (FollowUpCutoffs); Phase G4's
     * "closing_soon" those closing in those days, whatever their activity.
     */
    private function filtered(Workspace $workspace, array $filters, array $scope): Builder
    {
        $status    = $filters['status'] ?? null;
        $following = in_array($status, CrmOpportunity::FOLLOW_UP_STATUSES, true);

        return $this->visible($workspace, $scope)
            ->when($filters['search'] ?? null, fn (Builder $query, string $search) => $query->where(fn (Builder $query) => $query
                ->where('name', 'like', "%{$search}%")
                ->orWhereHas('contact', fn (Builder $query) => $query->where('name', 'like', "%{$search}%"))))
            ->when($status === 'archived', fn (Builder $query) => $query->whereNotNull('archived_at'), fn (Builder $query) => $query->whereNull('archived_at'))
            ->when($status === null || $status === '' || $following, fn (Builder $query) => $query->where('status', CrmOpportunity::OPEN))
            ->when($status === 'stale', fn (Builder $query) => $this->stale($query, $filters['stale_since'] ?? []))
            ->when($status === 'no_activity', fn (Builder $query) => $this->withoutNextActivity($query))
            ->when($status === 'quiet', fn (Builder $query) => $this->quiet($query, $filters['closing_from'], $filters['closing_to'], $filters['quiet_since']))
            ->when($status === 'closing_soon', fn (Builder $query) => $this->closingSoon($query, $filters['closing_from'], $filters['closing_to']))
            ->when($status && ! $following && ! in_array($status, ['all', 'archived'], true), fn (Builder $query) => $query->where('status', $status))
            ->when($filters['stage_id'] ?? null, fn (Builder $query, $id) => $query->where('stage_id', $id))
            ->when($filters['pipeline_id'] ?? null, fn (Builder $query, $id) => $query->where('pipeline_id', $id))
            ->when($filters['owner_id'] ?? null, fn (Builder $query, $id) => $query->where('owner_id', $id));
    }

    /** One of the opportunities $scope sees, with what its page shows. */
    public function findFor(Workspace $workspace, int $id, array $scope): ?CrmOpportunity
    {
        return $this->visible($workspace, $scope)->with(self::SHOWN_WITH)->find($id);
    }

    /**
     * $contactId's opportunities that $scope sees, archived ones aside, the
     * first added first, each with its stage and source: what its profile
     * shows (Phase F4).
     */
    public function forContactProfile(Workspace $workspace, array $scope, int $contactId): Collection
    {
        return $this->visible($workspace, $scope)
            ->where('contact_id', $contactId)
            ->whereNull('archived_at')
            ->with(['stage:id,name', 'source:id,name'])
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();
    }

    /**
     * $contactId's opportunities that $scope sees, by id, with their names:
     * what its timeline gathers (Phase E3).
     */
    public function ofContact(Workspace $workspace, array $scope, int $contactId): Collection
    {
        return $this->visible($workspace, $scope)
            ->where('contact_id', $contactId)
            ->get(['id', 'name'])
            ->keyBy('id');
    }

    /**
     * With what a sales document made from it starts with (Phase F1): its
     * contact, and its interested items with their items - a combo's with
     * its components, for its price.
     */
    public function loadForDocuments(CrmOpportunity $opportunity): CrmOpportunity
    {
        return $opportunity->load(['contact', 'interestedItems.item.components.component']);
    }

    /** $workspace's opportunity $id, whoever may see it: one won when its sales order is confirmed (Phase F2). */
    public function findInBusiness(Workspace $workspace, int $id): ?CrmOpportunity
    {
        return CrmOpportunity::query()->where('workspace_id', $workspace->id)->with(self::SHOWN_WITH)->find($id);
    }

    /** Made through the business, so its workspace is never read from $data. */
    public function create(Workspace $workspace, array $data): CrmOpportunity
    {
        return $workspace->crmOpportunities()->create($data);
    }

    public function update(CrmOpportunity $opportunity, array $data): CrmOpportunity
    {
        $opportunity->update($data);

        return $opportunity;
    }

    /** Read again after a save, with what its page shows. */
    public function loadShown(CrmOpportunity $opportunity): CrmOpportunity
    {
        return $opportunity->refresh()->load(self::SHOWN_WITH);
    }

    /** Marked changed now, by whoever is changing it. */
    public function touch(CrmOpportunity $opportunity): void
    {
        $opportunity->touch();
    }

    /** Its row, locked until the change is committed: two moves at once wait for each other. */
    public function lock(CrmOpportunity $opportunity): CrmOpportunity
    {
        return CrmOpportunity::query()->whereKey($opportunity->id)->lockForUpdate()->firstOrFail();
    }

    /** Its interested items as $rows say, in their order - each [item_id, name, quantity] - the ones it had replaced. */
    public function replaceItems(CrmOpportunity $opportunity, array $rows): void
    {
        CrmOpportunityItem::query()->where('crm_opportunity_id', $opportunity->id)->delete();

        foreach (array_values($rows) as $position => $row) {
            $opportunity->interestedItems()->create([...$row, 'position' => $position]);
        }
    }

    /** It leaves the stage it is at - if any - and is at $stageId from now, moved there by $userId. */
    public function enterStage(CrmOpportunity $opportunity, int $stageId, ?int $userId): void
    {
        $now = now();

        CrmStageVisit::query()->where('crm_opportunity_id', $opportunity->id)->whereNull('left_at')->update(['left_at' => $now, 'updated_at' => $now]);

        $opportunity->stageVisits()->create(['crm_stage_id' => $stageId, 'entered_at' => $now, 'entered_by' => $userId]);
    }

    /** Whether an opportunity is for $contact: one that has one is deactivated, never deleted. */
    public function usesContact(Contact $contact): bool
    {
        return CrmOpportunity::query()->where('contact_id', $contact->id)->exists();
    }

    /** Whether an opportunity is at $location: one that has one is deactivated, never deleted. */
    public function usesLocation(Location $location): bool
    {
        return CrmOpportunity::query()->where('location_id', $location->id)->exists();
    }

    /** Whether an opportunity came from $source: one that has is switched off, never deleted. */
    public function usesSource(CrmOption $source): bool
    {
        return CrmOpportunity::query()->where('source_id', $source->id)->exists();
    }

    /** Whether an opportunity was lost for $reason (Phase D2a): one that was is switched off, never deleted. */
    public function usesLostReason(CrmOption $reason): bool
    {
        return CrmOpportunity::query()->where('lost_reason_id', $reason->id)->exists();
    }

    /**
     * Of $stageIds, those an opportunity is in or has been at: a stage that
     * holds one, or its history, is switched off, never removed.
     *
     * @param  list<int>  $stageIds
     * @return list<int>
     */
    public function stagesInUse(array $stageIds): array
    {
        if ($stageIds === []) {
            return [];
        }

        $current = CrmOpportunity::query()->whereIn('stage_id', $stageIds)->distinct()->pluck('stage_id');
        $visited = CrmStageVisit::query()->whereIn('crm_stage_id', $stageIds)->distinct()->pluck('crm_stage_id');

        return $current->merge($visited)->map(fn ($id) => (int) $id)->unique()->values()->all();
    }

    /**
     * Those at a stage of $since, which they entered before its moment - by
     * each stage in turn, the same in MySQL and SQLite. No stage that goes
     * stale finds none.
     *
     * @param  array<int, \DateTimeInterface>  $since
     */
    private function stale(Builder $query, array $since): void
    {
        if ($since === []) {
            $query->whereRaw('1 = 0');

            return;
        }

        $query->where(function (Builder $query) use ($since) {
            foreach ($since as $stageId => $moment) {
                $query->orWhere(fn (Builder $query) => $query->where('stage_id', $stageId)->where('stage_entered_at', '<', $moment));
            }
        });
    }

    /** Those with no activity planned (Phase G3b) - an overdue one is still planned. */
    private function withoutNextActivity(Builder $query): void
    {
        $query->whereNotExists(fn ($activities) => $activities->selectRaw('1')
            ->from('crm_activities')
            ->whereColumn('crm_activities.opportunity_id', 'crm_opportunities.id')
            ->where('crm_activities.status', CrmActivity::PLANNED));
    }

    /** Those expected to close from $from to $to, business days (Phase G4): as the CRM Overview counts those closing soon. */
    private function closingSoon(Builder $query, string $from, string $to): void
    {
        $query->whereBetween('expected_close_date', [$from, $to]);
    }

    /**
     * Those expected to close from $from to $to - business days - that have
     * gone without activity since $since (Phase G3b): added before it, with
     * no activity done and no e-mail sent since, as a record's days without
     * activity count (RecordFollowUp).
     */
    private function quiet(Builder $query, string $from, string $to, CarbonInterface $since): void
    {
        $this->closingSoon($query, $from, $to);

        $query->where('crm_opportunities.created_at', '<', $since)
            ->whereNotExists(fn ($activities) => $activities->selectRaw('1')
                ->from('crm_activities')
                ->whereColumn('crm_activities.opportunity_id', 'crm_opportunities.id')
                ->where('crm_activities.status', CrmActivity::COMPLETED)
                ->where('crm_activities.completed_at', '>=', $since))
            ->whereNotExists(fn ($emails) => $emails->selectRaw('1')
                ->from('crm_emails')
                ->whereColumn('crm_emails.opportunity_id', 'crm_opportunities.id')
                ->where('crm_emails.created_at', '>=', $since));
    }

    /**
     * The CRM Overview's follow-up warnings (Phase G3b) among the open
     * opportunities $scope sees, archived ones left out, narrowed by
     * $filters (narrowed()): how many have no activity planned, are stale
     * (by $staleSince, see stale()), and close soon without recent activity
     * ($quiet: closing_from, closing_to and quiet_since - see quiet()).
     *
     * @param  array<int, \DateTimeInterface>  $staleSince
     * @param  array{closing_from: string, closing_to: string, quiet_since: CarbonInterface}  $quiet
     * @return array{without_next_activity: int, stale: int, quiet: int}
     */
    public function followUpCounts(Workspace $workspace, array $scope, array $filters, array $staleSince, array $quiet): array
    {
        $open = fn () => $this->narrowed($this->visible($workspace, $scope), $filters)->whereNull('archived_at')->where('status', CrmOpportunity::OPEN);

        return [
            'without_next_activity' => tap($open(), fn (Builder $query) => $this->withoutNextActivity($query))->count(),
            'stale'                 => tap($open(), fn (Builder $query) => $this->stale($query, $staleSince))->count(),
            'quiet'                 => tap($open(), fn (Builder $query) => $this->quiet($query, $quiet['closing_from'], $quiet['closing_to'], $quiet['quiet_since']))->count(),
        ];
    }

    /**
     * What the CRM Overview counts (Phase G1) among the opportunities $scope
     * sees, archived ones left out: the open ones, and those won or lost
     * between the business days $from and $to - narrowed by $filters
     * (narrowed()). Only what the figures need of each.
     */
    public function forOverview(Workspace $workspace, array $scope, array $filters, string $from, string $to): Collection
    {
        return $this->narrowed($this->visible($workspace, $scope), $filters)
            ->whereNull('archived_at')
            ->where(fn (Builder $query) => $query
                ->where('status', CrmOpportunity::OPEN)
                ->orWhere(fn (Builder $query) => $query->whereIn('status', [CrmOpportunity::WON, CrmOpportunity::LOST])->whereBetween('closed_on', [$from, $to])))
            ->get(['id', 'status', 'currency', 'expected_value', 'probability', 'expected_close_date', 'closed_on']);
    }

    /**
     * What the Forecast counts (Phase G2) among the opportunities $scope
     * sees, archived ones left out: the open ones, and those won between the
     * business days $from and $to - this month - narrowed by $filters
     * (narrowed()). Each with what it is grouped by: its owner, team and
     * stage, the stage's pipeline too.
     */
    public function forForecast(Workspace $workspace, array $scope, array $filters, string $from, string $to): Collection
    {
        return $this->narrowed($this->visible($workspace, $scope), $filters)
            ->whereNull('archived_at')
            ->where(fn (Builder $query) => $query
                ->where('status', CrmOpportunity::OPEN)
                ->orWhere(fn (Builder $query) => $query->where('status', CrmOpportunity::WON)->whereBetween('closed_on', [$from, $to])))
            ->with(['owner:id,first_name,last_name', 'team:id,name', 'stage:id,name,position,crm_pipeline_id', 'stage.pipeline:id,name,position'])
            ->get(['id', 'status', 'currency', 'expected_value', 'probability', 'expected_close_date', 'closed_on', 'owner_id', 'sales_team_id', 'stage_id']);
    }

    /**
     * What CRM's opportunity reports count (Phase H2): those $scope sees,
     * archived ones left out, narrowed by $filters (narrowed()) - the open
     * ones (only those with no activity planned when $withoutNextActivity),
     * or with $closedFrom and $closedTo those won or lost between those
     * business days - with what the reports name and group them by.
     */
    public function forReports(Workspace $workspace, array $scope, array $filters, ?string $closedFrom = null, ?string $closedTo = null, bool $withoutNextActivity = false): Collection
    {
        return $this->narrowed($this->visible($workspace, $scope), $filters)
            ->whereNull('archived_at')
            ->when(
                $closedFrom !== null,
                fn (Builder $query) => $query->whereIn('status', [CrmOpportunity::WON, CrmOpportunity::LOST])->whereBetween('closed_on', [$closedFrom, $closedTo]),
                fn (Builder $query) => $query->where('status', CrmOpportunity::OPEN),
            )
            ->when($withoutNextActivity, fn (Builder $query) => $this->withoutNextActivity($query))
            ->with(['contact:id,name', 'stage:id,name,position,crm_pipeline_id,stale_after_days', 'stage.pipeline:id,name,position', 'owner:id,first_name,last_name', 'team:id,name', 'source:id,name', 'lostReason:id,name'])
            ->get([
                'id', 'name', 'contact_id', 'status', 'pipeline_id', 'stage_id', 'owner_id', 'sales_team_id', 'source_id', 'lost_reason_id', 'lost_competitor',
                'currency', 'expected_value', 'probability', 'expected_close_date', 'closed_on', 'stage_entered_at', 'created_at',
            ]);
    }

    /**
     * The stage visits that ended between $from and $to (Phase H2's Stage
     * Duration) of the opportunities $scope sees, archived ones left out,
     * narrowed by $filters.
     */
    public function stageVisitsEnded(Workspace $workspace, array $scope, array $filters, CarbonInterface $from, CarbonInterface $to): Collection
    {
        return CrmStageVisit::query()
            ->whereIn('crm_opportunity_id', $this->narrowed($this->visible($workspace, $scope), $filters)->whereNull('archived_at')->select('crm_opportunities.id'))
            ->whereNotNull('left_at')
            ->whereBetween('left_at', [$from, $to])
            ->get(['id', 'crm_opportunity_id', 'crm_stage_id', 'entered_at', 'left_at']);
    }

    /** $query narrowed by the Overview's and Forecast's $filters: location_id, sales_team_id, owner_id, source_id, pipeline_id, stage_id and currency. */
    private function narrowed(Builder $query, array $filters): Builder
    {
        return $query
            ->when($filters['location_id'] ?? null, fn (Builder $query, $id) => $query->where('location_id', $id))
            ->when($filters['sales_team_id'] ?? null, fn (Builder $query, $id) => $query->where('sales_team_id', $id))
            ->when($filters['owner_id'] ?? null, fn (Builder $query, $id) => $query->where('owner_id', $id))
            ->when($filters['source_id'] ?? null, fn (Builder $query, $id) => $query->where('source_id', $id))
            ->when($filters['pipeline_id'] ?? null, fn (Builder $query, $id) => $query->where('pipeline_id', $id))
            ->when($filters['stage_id'] ?? null, fn (Builder $query, $id) => $query->where('stage_id', $id))
            ->when($filters['currency'] ?? null, fn (Builder $query, string $code) => $query->where('currency', $code));
    }

    /** $workspace's opportunities that $scope sees (OpportunityVisibility::scopeFor). */
    private function visible(Workspace $workspace, array $scope): Builder
    {
        return CrmOpportunity::query()
            ->where('workspace_id', $workspace->id)
            ->atLocations($scope['location_ids'])
            ->when(! $scope['all'], fn (Builder $query) => $query->where(function (Builder $query) use ($scope) {
                $query->where('owner_id', $scope['user_id'])
                    ->when($scope['member_ids'], fn (Builder $query, array $ids) => $query->orWhereIn('owner_id', $ids))
                    ->when($scope['team_ids'], fn (Builder $query, array $ids) => $query->orWhereIn('sales_team_id', $ids));
            }));
    }
}
