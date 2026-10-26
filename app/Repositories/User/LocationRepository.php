<?php

namespace App\Repositories\User;

use App\Models\User\Location;
use App\Models\User\Workspace;
use Illuminate\Database\Eloquent\Collection;

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

    /**
     * Made through the business, so its workspace and owner are always the
     * business's own - never read from $data.
     */
    public function create(Workspace $workspace, array $data): Location
    {
        return $workspace->locations()->create([...$data, 'user_id' => $workspace->user_id]);
    }

    /**
     * The business's location matching $attributes, made from them if there is
     * none yet - for something that may run twice and must still leave only one.
     */
    public function firstOrCreateFor(Workspace $workspace, array $attributes): Location
    {
        return $workspace->locations()->firstOrCreate($attributes, ['user_id' => $workspace->user_id]);
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
