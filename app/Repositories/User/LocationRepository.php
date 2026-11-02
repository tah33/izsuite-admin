<?php

namespace App\Repositories\User;

use App\Models\User\Location;
use App\Models\User\Workspace;
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

    public function findFor(Workspace $workspace, int $id): ?Location
    {
        return $workspace->locations()->find($id);
    }

    public function existsFor(Workspace $workspace): bool
    {
        return $workspace->locations()->exists();
    }

    public function countFor(Workspace $workspace): int
    {
        return $workspace->locations()->count();
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
