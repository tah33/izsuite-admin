<?php

namespace App\Repositories\User;

use App\Models\User\Location;
use App\Models\User\Workspace;
use App\Models\User\WorkspaceMember;
use App\Repositories\Shared\RecordAuthorRepository;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Locations belong to one business (workspace). Every lookup is scoped to it,
 * so another business's location - the same owner's or anyone else's - is
 * simply not found.
 */
class LocationRepository
{
    /**
     * Alphabetical, with the id as tie-breaker so two locations sharing a name
     * always come back in the same order.
     */
    public function getFor(Workspace $workspace): Collection
    {
        return $workspace->locations()->orderBy('name')->orderBy('id')->get();
    }

    /**
     * As getFor() - only those of $ids, when given - each with how many of
     * the business's users may use it (Doc 12 section 16): those with All
     * Locations and those it was chosen for, invited or active.
     *
     * @param  list<int>|null  $ids
     */
    public function listFor(Workspace $workspace, ?array $ids): Collection
    {
        $everywhere = $workspace->members()
            ->whereIn('status', WorkspaceMember::COUNTED)
            ->where('location_access', WorkspaceMember::ALL_LOCATIONS)
            ->count();

        return $workspace->locations()
            ->with(RecordAuthorRepository::WITH)
            ->when($ids !== null, fn (Builder $query) => $query->whereIn('locations.id', $ids))
            ->withCount(['members as chosen_count' => fn (Builder $query) => $query
                ->whereIn('workspace_members.status', WorkspaceMember::COUNTED)
                ->where('workspace_members.location_access', WorkspaceMember::SELECTED_LOCATIONS)])
            ->orderBy('name')
            ->orderBy('id')
            ->get()
            ->each(fn (Location $location) => $location->setAttribute('users_count', $everywhere + (int) $location->chosen_count));
    }

    public function findFor(Workspace $workspace, int $id): ?Location
    {
        return $workspace->locations()->find($id);
    }

    public function existsFor(Workspace $workspace): bool
    {
        return $workspace->locations()->exists();
    }

    /** Its active locations - what a plan's location limit counts (Doc 13 section 14). */
    public function countActiveFor(Workspace $workspace): int
    {
        return $workspace->locations()->where('is_active', true)->count();
    }

    /** Its active locations, the Primary first, for choosing which stay active (Doc 13 section 14). */
    public function activeFor(Workspace $workspace): Collection
    {
        return $workspace->locations()->where('is_active', true)->orderByDesc('is_primary')->orderBy('name')->get(['id', 'name', 'is_primary']);
    }

    /** Whether the business has marked one of its locations as its Primary. */
    public function hasPrimary(Workspace $workspace): bool
    {
        return $workspace->locations()->where('is_primary', true)->exists();
    }

    /** The business's Primary location, if it has marked one. */
    public function primaryFor(Workspace $workspace): ?Location
    {
        return $workspace->locations()->where('is_primary', true)->first();
    }

    /**
     * The names of those of $ids that are the business's locations, by id.
     *
     * @return array<int, string>
     */
    public function namesFor(Workspace $workspace, array $ids): array
    {
        return $ids ? $workspace->locations()->whereIn('id', $ids)->pluck('name', 'id')->all() : [];
    }

    /** $location the business's Primary, and no other. */
    public function makePrimary(Location $location): Location
    {
        DB::transaction(function () use ($location) {
            Location::query()->where('workspace_id', $location->workspace_id)->whereKeyNot($location->id)->update(['is_primary' => false]);

            $location->update(['is_primary' => true]);
        });

        return $location;
    }

    /**
     * Made through the business, so its workspace and owner are always the
     * business's own - never read from $data.
     */
    public function create(Workspace $workspace, array $data): Location
    {
        return $workspace->locations()->create([...$data, 'user_id' => $workspace->user_id]);
    }

    /**
     * The business's location matching $attributes, made from them and
     * $values if there is none yet - for something that may run twice and
     * must still leave only one.
     */
    public function firstOrCreateFor(Workspace $workspace, array $attributes, array $values = []): Location
    {
        return $workspace->locations()->firstOrCreate($attributes, [...$values, 'user_id' => $workspace->user_id]);
    }

    public function update(Location $location, array $data): Location
    {
        $location->update($data);

        return $location;
    }

    public function delete(Location $location): void
    {
        $location->delete();
    }
}
