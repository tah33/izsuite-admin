<?php

namespace App\Repositories\User;

use App\Models\User\Location;
use App\Models\User\User;
use Illuminate\Database\Eloquent\Collection;

class LocationRepository
{
    /**
     * Alphabetical, with the id as tie-breaker so two locations sharing a name
     * always come back in the same order.
     */
    public function getForUser(User $user): Collection
    {
        return Location::where('user_id', $user->id)->orderBy('name')->orderBy('id')->get();
    }

    /**
     * Scoped to the owner: another account's id finds nothing, so callers
     * cannot tell "not yours" from "does not exist".
     */
    public function findForUser(User $user, int $id): ?Location
    {
        return Location::where('user_id', $user->id)->find($id);
    }

    public function create(User $user, array $data): Location
    {
        return $user->locations()->create($data);
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
