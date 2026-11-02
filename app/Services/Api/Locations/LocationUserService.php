<?php

namespace App\Services\Api\Locations;

use App\Models\User\Location;
use App\Models\User\User;
use App\Models\User\Workspace;
use App\Models\User\WorkspaceMember;
use App\Repositories\User\LocationRepository;
use App\Repositories\User\WorkspaceMemberRepository;
use App\Services\Shared\ActivityLogService;
use App\Services\Shared\WorkspaceAccess;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * A location's Assign Users (Doc 12 section 18): which of the users with
 * Selected Locations may use it. Users with All Locations already may. Only
 * an active location is given to more users; nobody changes their own
 * locations (section 22) or loses their last one, and one who loses their
 * default location gets the first of those left. Each change is in the
 * Activity Log (section 24: Location Assignment).
 */
class LocationUserService
{
    public function __construct(
        protected LocationRepository $locationRepository,
        protected WorkspaceMemberRepository $memberRepository,
        protected WorkspaceAccess $access,
    ) {}

    /** $memberIds are validated (see AssignLocationUsersRequest): the users with Selected Locations who may use location $id - they and no others. */
    public function assign(Workspace $workspace, User $actor, int $id, array $memberIds): Location
    {
        $location = $this->locationRepository->findFor($workspace, $id);

        abort_unless($location && $this->access->usesLocation($location->id), 404, 'Location not found.');

        $changes = $this->changes($location, $actor, array_map('intval', $memberIds), $this->memberRepository->withChosenLocations($workspace));

        DB::transaction(function () use ($changes, $location) {
            foreach ($changes as [$member, $gets]) {
                $this->memberRepository->toggleLocation($member, $location->id, $gets);

                if (! $gets && $member->default_location_id === $location->id) {
                    $this->memberRepository->update($member, ['default_location_id' => $member->locations->firstWhere('id', '!=', $location->id)?->id]);
                }
            }
        });

        foreach ($changes as [$member, $gets]) {
            $name  = Str::limit($member->name, 80);
            $place = Str::limit($location->name, 80);

            ActivityLogService::record('updated', $gets ? "Gave \"{$name}\" access to location \"{$place}\"" : "Took location \"{$place}\" from \"{$name}\"", $member, ['source' => 'api']);
        }

        return $this->locationRepository->listFor($workspace, [$location->id])->first();
    }

    /**
     * Each member whose access to $location changes, and whether they gain it.
     *
     * @return list<array{0: WorkspaceMember, 1: bool}>
     */
    private function changes(Location $location, User $actor, array $chosen, iterable $members): array
    {
        $changes = [];

        foreach ($members as $member) {
            $gets = in_array($member->id, $chosen, true);

            if ($member->locations->contains('id', $location->id) === $gets) {
                continue;
            }

            abort_if($member->user_id === $actor->id, 403, 'You cannot change your own locations.');

            if ($gets && ! $location->is_active) {
                throw ValidationException::withMessages(['member_ids' => 'Activate this location before giving more users access to it.']);
            }

            if (! $gets && $member->locations->count() === 1) {
                throw ValidationException::withMessages(['member_ids' => "{$member->name} would have no location left. Give them another location first."]);
            }

            $changes[] = [$member, $gets];
        }

        return $changes;
    }
}
