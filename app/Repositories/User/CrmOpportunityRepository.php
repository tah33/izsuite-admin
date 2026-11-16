<?php

namespace App\Repositories\User;

use App\Models\User\Contact;
use App\Models\User\CrmOpportunity;
use App\Models\User\CrmOpportunityItem;
use App\Models\User\CrmOption;
use App\Models\User\CrmStageVisit;
use App\Models\User\Location;
use App\Models\User\Workspace;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * A business's opportunities (Doc 17 Phases C1 and D1), each query of the
 * Opportunities page narrowed to those the signed-in user sees
 * (OpportunityVisibility's scope).
 */
class CrmOpportunityRepository
{
    private const SHOWN_WITH = [
        'contact:id,name,contact_number', 'owner:id,first_name,last_name', 'team:id,name', 'location:id,name', 'source:id,name',
        'stage:id,name,probability,category', 'pipeline.stages', 'lead:id,name', 'creator:id,first_name,last_name',
        'interestedItems', 'stageVisits.stage:id,name', 'stageVisits.mover:id,first_name,last_name',
    ];

    /**
     * The Opportunities page: open ones by expected closing date, soonest
     * first - or those of one status, or all of them - searched by their name
     * or their contact's, by stage and by owner.
     */
    public function paginateFor(Workspace $workspace, array $filters, array $scope, int $perPage): LengthAwarePaginator
    {
        $status = $filters['status'] ?? null;

        return $this->visible($workspace, $scope)
            ->with(['contact:id,name', 'stage:id,name', 'owner:id,first_name,last_name'])
            ->when($filters['search'] ?? null, fn (Builder $query, string $search) => $query->where(fn (Builder $query) => $query
                ->where('name', 'like', "%{$search}%")
                ->orWhereHas('contact', fn (Builder $query) => $query->where('name', 'like', "%{$search}%"))))
            ->when($status === null || $status === '', fn (Builder $query) => $query->where('status', CrmOpportunity::OPEN))
            ->when($status && $status !== 'all', fn (Builder $query) => $query->where('status', $status))
            ->when($filters['stage_id'] ?? null, fn (Builder $query, $id) => $query->where('stage_id', $id))
            ->when($filters['owner_id'] ?? null, fn (Builder $query, $id) => $query->where('owner_id', $id))
            ->orderBy('expected_close_date')
            ->orderBy('id')
            ->paginate($perPage);
    }

    /** One of the opportunities $scope sees, with what its page shows. */
    public function findFor(Workspace $workspace, int $id, array $scope): ?CrmOpportunity
    {
        return $this->visible($workspace, $scope)->with(self::SHOWN_WITH)->find($id);
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
