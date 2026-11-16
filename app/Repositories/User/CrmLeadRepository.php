<?php

namespace App\Repositories\User;

use App\Models\User\CrmLead;
use App\Models\User\CrmLeadItem;
use App\Models\User\CrmOption;
use App\Models\User\Location;
use App\Models\User\Workspace;
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

    /**
     * The Leads page: open leads newest first - or those of one status, or
     * all of them - searched by name, business, e-mail or phone, by owner and
     * by source.
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
            ->when($status && $status !== 'all', fn (Builder $query) => $query->where('status', $status))
            ->when($filters['owner_id'] ?? null, fn (Builder $query, $id) => $query->where('owner_id', $id))
            ->when($filters['source_id'] ?? null, fn (Builder $query, $id) => $query->where('source_id', $id))
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
