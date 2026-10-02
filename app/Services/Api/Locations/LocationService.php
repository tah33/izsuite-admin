<?php

namespace App\Services\Api\Locations;

use App\Models\User\Location;
use App\Models\User\User;
use App\Repositories\User\LocationRepository;
use App\Services\Shared\ActivityLogService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

class LocationService
{
    public function __construct(
        protected LocationRepository $locationRepository,
    ) {}

    public function list(User $user): Collection
    {
        return $this->locationRepository->getForUser($user);
    }

    public function create(User $user, array $data): Location
    {
        $location = $this->locationRepository->create($user, $data);

        // Each write is logged here, by name, so LogApiWriteActivity does not
        // add its own vaguer "POST api/v1/locations" entry for the same request.
        ActivityLogService::record('created', "Added location {$this->quoted($location)}", $location, ['source' => 'api']);

        return $location;
    }

    public function update(User $user, int $id, array $data): Location
    {
        $location = $this->findOwned($user, $id);

        $this->locationRepository->update($location, $data);

        ActivityLogService::record('updated', "Updated location {$this->quoted($location)}", $location, [
            'source' => 'api',
            'fields' => array_keys(array_diff_key($location->getChanges(), ['updated_at' => true])),
        ]);

        return $location;
    }

    public function delete(User $user, int $id): void
    {
        $location = $this->findOwned($user, $id);

        $this->locationRepository->delete($location);

        ActivityLogService::record('deleted', "Deleted location {$this->quoted($location)}", $location, ['source' => 'api']);
    }

    /**
     * The name, in quotes, for an activity log description.
     *
     * activity_logs.description is a 255-character column and a location name
     * may be as long as 255 itself, so the name is cut down: left whole, a
     * valid long name would make the log insert fail after the location was
     * already saved, and the request would answer 500 for a write that worked.
     */
    private function quoted(Location $location): string
    {
        return '"'.Str::limit($location->name, 100).'"';
    }

    /**
     * A location that is someone else's answers exactly like one that does not
     * exist. Aborting with our own message also keeps Laravel's default out of
     * the response - "No query results for model [App\Models\User\Location]" -
     * which names an internal class.
     */
    private function findOwned(User $user, int $id): Location
    {
        return $this->locationRepository->findForUser($user, $id)
            ?? abort(404, 'Location not found.');
    }
}
