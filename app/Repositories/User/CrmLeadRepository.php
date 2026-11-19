<?php

namespace App\Repositories\User;

use App\Models\User\CrmLead;
use App\Models\User\CrmLeadItem;
use App\Models\User\CrmOption;
use App\Models\User\Location;
use App\Models\User\Workspace;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * A business's leads (Doc 17 Phase B1), each query narrowed to the leads the
 * signed-in user sees (LeadVisibility's scope).
 */
class CrmLeadRepository
{
    private const SHOWN_WITH = [
        'source:id,name', 'owner:id,first_name,last_name', 'team:id,name', 'location:id,name',
        'creator:id,first_name,last_name',
        // Phase B2a.
        'interestedItems',
        // Phase C1: what it was converted to.
        'contact:id,name', 'opportunity:id,name,stage_id,expected_value,currency,expected_close_date', 'opportunity.stage:id,name',
        'converter:id,first_name,last_name',
    ];

    /** What lead scoring (Phase I2) reads of a lead. */
    private const SCORED = [
        'id', 'workspace_id', 'status', 'source_id', 'location_id', 'email', 'phone', 'business_name', 'job_title', 'address_line1', 'address_city',
        'decision_maker', 'customer_need', 'estimated_value', 'budget', 'currency', 'purchase_period', 'business_size',
        'contacted_at', 'first_contacted_at', 'created_at', 'score', 'score_factors',
    ];

    /**
     * The Leads page: open leads newest first - or those of one status, or
     * all of them - searched by name, business, e-mail or phone, by owner and
     * by source. Phase G3b's "uncontacted" is the open ones not contacted in
     * time: added before $filters['uncontacted_before'] (FollowUpCutoffs).
     * Phase G4's "awaiting" is those awaiting follow-up, as overview() counts
     * them. Phase I2's score is a band of CrmLead::SCORE_BANDS.
     */
    public function paginateFor(Workspace $workspace, array $filters, array $scope, int $perPage): LengthAwarePaginator
    {
        $status = $filters['status'] ?? null;

        return $this->visible($workspace, $scope)
            ->with(['source:id,name', 'owner:id,first_name,last_name'])
            ->when($filters['search'] ?? null, fn (Builder $query, string $search) => $query->where(fn (Builder $query) => $query
                ->where('name', 'like', "%{$search}%")
                ->orWhere('business_name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")
                ->orWhere('phone', 'like', "%{$search}%")))
            ->when($status === null || $status === '', fn (Builder $query) => $query->whereIn('status', CrmLead::OPEN))
            ->when($status === 'uncontacted', fn (Builder $query) => $this->uncontacted($query, $filters['uncontacted_before']))
            ->when($status === 'awaiting', fn (Builder $query) => $query->whereIn('status', CrmLead::AWAITING))
            ->when($status && ! in_array($status, ['all', 'awaiting', 'uncontacted'], true), fn (Builder $query) => $query->where('status', $status))
            ->when($filters['owner_id'] ?? null, fn (Builder $query, $id) => $query->where('owner_id', $id))
            ->when($filters['source_id'] ?? null, fn (Builder $query, $id) => $query->where('source_id', $id))
            // Phase I2: a score's band, hot, warm or cold.
            ->when(CrmLead::SCORE_BANDS[$filters['score'] ?? ''] ?? null, fn (Builder $query, array $band) => $query->whereBetween('score', $band))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    /** One of the leads $scope sees, with what its page shows. */
    public function findFor(Workspace $workspace, int $id, array $scope): ?CrmLead
    {
        return $this->visible($workspace, $scope)->with(self::SHOWN_WITH)->find($id);
    }

    public function create(Workspace $workspace, array $data): CrmLead
    {
        return $workspace->crmLeads()->create($data);
    }

    /**
     * The leads $scope sees that share any of $keys - each a list of tidied
     * e-mails, phones and business names (LeadMatchKeys) - with their owners,
     * $exceptIds aside: possible duplicates (Phase C2).
     *
     * @param  array{emails: list<string>, phones: list<string>, businesses: list<string>}  $keys
     */
    public function matching(Workspace $workspace, array $scope, array $keys, array $exceptIds): \Illuminate\Database\Eloquent\Collection
    {
        if (! array_filter($keys)) {
            return new \Illuminate\Database\Eloquent\Collection;
        }

        return $this->visible($workspace, $scope)
            ->whereNotIn('id', $exceptIds)
            ->where(fn (Builder $query) => $query
                ->when($keys['emails'], fn (Builder $query, array $values) => $query->orWhereIn('match_email', $values))
                ->when($keys['phones'], fn (Builder $query, array $values) => $query->orWhereIn('match_phone', $values))
                ->when($keys['businesses'], fn (Builder $query, array $values) => $query->orWhereIn('match_business', $values)))
            ->with('owner:id,first_name,last_name')
            ->orderByDesc('id')
            ->limit(200)
            ->get(['id', 'name', 'business_name', 'status', 'owner_id', 'match_email', 'match_phone', 'match_business']);
    }

    /** Of $ids, the leads $scope sees, by id: those a decision names that no longer match. */
    public function visibleAmong(Workspace $workspace, array $scope, array $ids): \Illuminate\Database\Eloquent\Collection
    {
        return $ids === [] ? new \Illuminate\Database\Eloquent\Collection : $this->visible($workspace, $scope)
            ->whereIn('id', $ids)
            ->with('owner:id,first_name,last_name')
            ->get(['id', 'name', 'business_name', 'status', 'owner_id', 'match_email', 'match_phone', 'match_business'])
            ->keyBy('id');
    }

    /**
     * The leads converted into $contactId that $scope sees, the first
     * converted first, each with its source: where the contact came from
     * (Phase F4).
     */
    public function convertedInto(Workspace $workspace, array $scope, int $contactId): \Illuminate\Database\Eloquent\Collection
    {
        return $this->visible($workspace, $scope)
            ->where('contact_id', $contactId)
            ->whereNotNull('converted_at')
            ->with('source:id,name')
            ->orderBy('converted_at')
            ->orderBy('id')
            ->get(['id', 'name', 'source_id', 'converted_at']);
    }

    /**
     * The leads converted into $contactId that $scope sees, by id, with their
     * names: what its timeline gathers (Phase E3).
     */
    public function ofContact(Workspace $workspace, array $scope, int $contactId): \Illuminate\Database\Eloquent\Collection
    {
        return $this->visible($workspace, $scope)
            ->where('contact_id', $contactId)
            ->get(['id', 'name'])
            ->keyBy('id');
    }

    public function update(CrmLead $lead, array $data): CrmLead
    {
        $lead->update($data);

        return $lead;
    }

    /** Read again after a save, with what its page shows. */
    public function loadShown(CrmLead $lead): CrmLead
    {
        return $lead->refresh()->load(self::SHOWN_WITH);
    }

    /** Marked changed now, by whoever is changing it. */
    public function touch(CrmLead $lead): void
    {
        $lead->touch();
    }

    /**
     * Its first contact (Phase G3a) at $at, when it has none yet - its
     * version kept (EditConflicts): nothing on its form changed, so someone
     * editing it saves as before.
     */
    public function answered(int $leadId, CarbonInterface $at): void
    {
        CrmLead::query()->whereKey($leadId)->whereNull('first_contacted_at')->toBase()->update(['first_contacted_at' => $at]);
    }

    /**
     * Its interested items as $rows say, in their order - each [item_id,
     * name, quantity] - the ones it had replaced. Nothing points at them.
     */
    public function replaceItems(CrmLead $lead, array $rows): void
    {
        CrmLeadItem::query()->where('crm_lead_id', $lead->id)->delete();

        foreach (array_values($rows) as $position => $row) {
            $lead->interestedItems()->create([...$row, 'position' => $position]);
        }
    }

    /** Whether a lead came from $source: one that has is switched off, never deleted. */
    public function usesSource(CrmOption $source): bool
    {
        return CrmLead::query()->where('source_id', $source->id)->exists();
    }

    /** Whether a lead is at $location: one that has one is deactivated, never deleted. */
    public function usesLocation(Location $location): bool
    {
        return CrmLead::query()->where('location_id', $location->id)->exists();
    }

    /**
     * The CRM Overview's lead figures (Phase G1) among those $scope sees,
     * narrowed by $filters (location_id, sales_team_id, owner_id, source_id):
     * how many were added between $from and $to, and how many of those are
     * converted; how many are open and not contacted yet (New or Assigned);
     * and how many are qualified, not yet converted.
     *
     * @return array{new: int, converted: int, awaiting: int, qualified: int}
     */
    public function overview(Workspace $workspace, array $scope, array $filters, CarbonInterface $from, CarbonInterface $to): array
    {
        $leads = fn () => $this->narrowed($this->visible($workspace, $scope), $filters);

        return [
            'new'       => $leads()->whereBetween('created_at', [$from, $to])->count(),
            'converted' => $leads()->whereBetween('created_at', [$from, $to])->where('status', CrmLead::CONVERTED)->count(),
            'awaiting'  => $leads()->whereIn('status', CrmLead::AWAITING)->count(),
            'qualified' => $leads()->where('status', CrmLead::QUALIFIED)->count(),
        ];
    }

    /**
     * The CRM Overview's lead warnings (Phase G3b) among those $scope sees,
     * narrowed as overview()'s: how many wait in the queue (New: nobody owns
     * them), and how many are open and not contacted in time - added before
     * $uncontactedBefore, never contacted.
     *
     * @return array{unassigned: int, uncontacted: int}
     */
    public function followUpCounts(Workspace $workspace, array $scope, array $filters, CarbonInterface $uncontactedBefore): array
    {
        $leads = fn () => $this->narrowed($this->visible($workspace, $scope), $filters);

        return [
            'unassigned'  => $leads()->where('status', CrmLead::NEW)->count(),
            'uncontacted' => tap($leads(), fn (Builder $query) => $this->uncontacted($query, $uncontactedBefore))->count(),
        ];
    }

    /**
     * What CRM's lead reports count (Phase H1): the leads $scope sees added
     * between $from and $to, narrowed as overview()'s - each with its status,
     * when it was added and first contacted, and the source, owner and sales
     * team it is grouped by.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, CrmLead>
     */
    public function forReports(Workspace $workspace, array $scope, array $filters, CarbonInterface $from, CarbonInterface $to): \Illuminate\Database\Eloquent\Collection
    {
        return $this->narrowed($this->visible($workspace, $scope), $filters)
            ->whereBetween('created_at', [$from, $to])
            ->with(['source:id,name', 'owner:id,first_name,last_name', 'team:id,name'])
            ->get(['id', 'status', 'source_id', 'owner_id', 'sales_team_id', 'created_at', 'first_contacted_at']);
    }

    /**
     * How many open leads each of $ownerIds has (Phase I1's workload rules),
     * by owner id - none, for one who has none.
     *
     * @return array<int, int>
     */
    public function openCountsByOwner(Workspace $workspace, array $ownerIds): array
    {
        return CrmLead::query()
            ->where('workspace_id', $workspace->id)
            ->whereIn('owner_id', $ownerIds)
            ->whereIn('status', CrmLead::OPEN)
            ->groupBy('owner_id')
            ->selectRaw('owner_id, count(*) as open_count')
            ->pluck('open_count', 'owner_id')
            ->map(fn ($count) => (int) $count)
            ->all();
    }

    /**
     * Leads $ids of $workspace with what LeadScoring reads (Phase I2): their
     * details, value, budget, qualification, first contact and score.
     */
    public function forScoring(Workspace $workspace, array $ids): \Illuminate\Database\Eloquent\Collection
    {
        return CrmLead::query()->where('workspace_id', $workspace->id)->whereIn('id', $ids)->get(self::SCORED);
    }

    /** Each run of up to 200 of $workspace's open leads, as forScoring() reads them, to $each. */
    public function eachOpenForScoring(Workspace $workspace, callable $each): void
    {
        CrmLead::query()->where('workspace_id', $workspace->id)->whereIn('status', CrmLead::OPEN)->select(self::SCORED)->chunkById(200, $each);
    }

    /**
     * What leads $ids are interested in (Phase B2a), by lead id: the ids of
     * the items named, none for those typed in. A lead interested in nothing
     * has no entry.
     *
     * @return array<int, list<int>>
     */
    public function interestsOf(array $ids): array
    {
        return CrmLeadItem::query()
            ->whereIn('crm_lead_id', $ids)
            ->get(['crm_lead_id', 'item_id'])
            ->groupBy('crm_lead_id')
            ->map(fn ($rows) => $rows->pluck('item_id')->filter()->map(fn ($id) => (int) $id)->values()->all())
            ->all();
    }

    /**
     * Its score and factors (Phase I2), quietly: they are not a change to the
     * lead, so its version stays and someone editing it saves as before.
     *
     * @param  list<array{name: string, points: int}>|null  $factors
     */
    public function scored(int $leadId, ?int $score, ?array $factors): void
    {
        CrmLead::query()->whereKey($leadId)->toBase()->update(['score' => $score, 'score_factors' => $factors === null ? null : json_encode($factors)]);
    }

    /** No lead of $workspace has a score any more, quietly: how many had one. */
    public function unscoreAll(Workspace $workspace): int
    {
        return CrmLead::query()->where('workspace_id', $workspace->id)->whereNotNull('score')->toBase()->update(['score' => null, 'score_factors' => null]);
    }

    /** Open, never contacted (Phase G3a's first contact), and added before $before. */
    private function uncontacted(Builder $query, CarbonInterface $before): void
    {
        $query->whereIn('status', CrmLead::OPEN)->whereNull('first_contacted_at')->where('created_at', '<', $before);
    }

    /** $query narrowed by the Overview's $filters a lead has: location_id, sales_team_id, owner_id and source_id. */
    private function narrowed(Builder $query, array $filters): Builder
    {
        return $query
            ->when($filters['location_id'] ?? null, fn (Builder $query, $id) => $query->where('location_id', $id))
            ->when($filters['sales_team_id'] ?? null, fn (Builder $query, $id) => $query->where('sales_team_id', $id))
            ->when($filters['owner_id'] ?? null, fn (Builder $query, $id) => $query->where('owner_id', $id))
            ->when($filters['source_id'] ?? null, fn (Builder $query, $id) => $query->where('source_id', $id));
    }

    /** $workspace's leads that $scope sees (LeadVisibility::scopeFor). */
    private function visible(Workspace $workspace, array $scope): Builder
    {
        return CrmLead::query()
            ->where('workspace_id', $workspace->id)
            ->atLocations($scope['location_ids'])
            ->when(! $scope['all'], fn (Builder $query) => $query->where(function (Builder $query) use ($scope) {
                $query->where('owner_id', $scope['user_id'])
                    ->when($scope['queue'], fn (Builder $query) => $query->orWhereNull('owner_id'))
                    ->when($scope['member_ids'], fn (Builder $query, array $ids) => $query->orWhereIn('owner_id', $ids))
                    ->when($scope['team_ids'], fn (Builder $query, array $ids) => $query->orWhereIn('sales_team_id', $ids));
            }));
    }
}
