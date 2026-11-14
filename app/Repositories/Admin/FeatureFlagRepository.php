<?php

namespace App\Repositories\Admin;

use App\Models\Admin\FeatureFlag;
use App\Models\User\Workspace;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/** Feature flags (Doc 15 section 15.12 - Phase E1). */
class FeatureFlagRepository
{
    /** @return Collection<int, FeatureFlag> every flag switched on - all Features needs to answer */
    public function switchedOn(): Collection
    {
        return FeatureFlag::where('status', FeatureFlag::ON)->get(['id', 'key', 'status', 'businesses', 'countries', 'packages', 'percentage', 'starts_at', 'ends_at']);
    }

    /** Admin > Feature Flags: newest first. $filters: search (name or key) and status (on or off). */
    public function page(array $filters, int $perPage): LengthAwarePaginator
    {
        return FeatureFlag::with('responsible:id,first_name,last_name')
            ->when($filters['search'] ?? null, function (Builder $query, string $search) {
                $like = '%'.addcslashes($search, '%_\\').'%';
                $query->where(fn (Builder $query) => $query->where('name', 'like', $like)->orWhere('key', 'like', $like));
            })
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->latest('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function find(int $id): ?FeatureFlag
    {
        return FeatureFlag::find($id);
    }

    public function create(array $data): FeatureFlag
    {
        return FeatureFlag::create($data);
    }

    public function update(FeatureFlag $flag, array $data): FeatureFlag
    {
        $flag->update($data);

        return $flag;
    }

    public function delete(FeatureFlag $flag): void
    {
        $flag->delete();
    }

    /** @return list<int> which of $ids are businesses */
    public function businessIds(array $ids): array
    {
        return Workspace::whereIn('id', $ids)->pluck('id')->all();
    }

    /** @return array<int, string> $ids' businesses by ID, as BW- IDs and names */
    public function businessLabels(array $ids): array
    {
        return Workspace::whereIn('id', $ids)->get(['id', 'name'])->mapWithKeys(fn (Workspace $workspace) => [$workspace->id => $workspace->businessId().' '.$workspace->name])->all();
    }

    /** $workspace's country, from its business details. */
    public function countryOf(Workspace $workspace): ?string
    {
        return $workspace->businessSetting()->value('country');
    }
}
