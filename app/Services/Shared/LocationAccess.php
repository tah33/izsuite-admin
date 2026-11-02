<?php

namespace App\Services\Shared;

use App\Models\User\Location;
use App\Models\User\Workspace;
use App\Repositories\User\LocationRepository;
use App\Repositories\User\RecordLocationRepository;
use App\Repositories\User\WorkspaceMemberRepository;
use App\Support\RouteRecords;
use Illuminate\Http\Request;

/**
 * Step 4 of Doc 12's access order (section 2): a member reaches a record
 * only at a location they may use (section 20), whatever their role. A
 * payment account for the whole business is anyone's; a record at no
 * location is for those who may use every location. And where a new
 * transaction starts: the user's default location.
 */
class LocationAccess
{
    public const REFUSED = 'This record is at a location you do not have access to.';

    public function __construct(
        protected WorkspaceAccess $access,
        protected RecordLocationRepository $records,
        protected LocationRepository $locations,
        protected WorkspaceMemberRepository $members,
    ) {}

    /**
     * Where the signed-in user's new transaction starts (Doc 12 section 20):
     * their default location while it is active and theirs to use, else the
     * first active one they may use - none while there is none.
     */
    public function defaultFor(Workspace $workspace): ?int
    {
        $user    = app(Request::class)->user();
        $usable  = $this->locations->getFor($workspace)->filter(fn (Location $location) => $location->is_active && $this->access->usesLocation($location->id));
        $default = $user ? $this->members->findForUser($workspace, $user)?->default_location_id : null;

        return $usable->firstWhere('id', $default)?->id ?? $usable->first()?->id;
    }

    /** Refuses the request's record (RouteRecords) when it is at a location the user may not use. */
    public function authorizeRoute(Request $request, Workspace $workspace): void
    {
        $record = RouteRecords::for($request);

        if ($record !== null) {
            abort_unless($this->reaches($workspace, ...$record), 403, self::REFUSED);
        }
    }

    /**
     * Whether the signed-in user may reach record $id of $kind - true for one
     * the business does not have, which its own route then cannot find.
     */
    public function reaches(Workspace $workspace, string $kind, int $id): bool
    {
        $locations = $this->access->locations();

        if ($locations === null) {
            return true;
        }

        $at     = $this->records->locationsOf($workspace, $kind, $id);
        $shared = in_array($kind, [RouteRecords::PAYMENT_ACCOUNT, RouteRecords::ACCOUNT_TRANSFER], true);

        return $at === null || collect($at)->every(fn (?int $location) => $location === null ? $shared : in_array($location, $locations, true));
    }
}
