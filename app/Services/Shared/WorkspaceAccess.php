<?php

namespace App\Services\Shared;

use App\Enums\DocumentType;
use App\Models\User\User;
use App\Models\User\Workspace;
use App\Models\User\WorkspaceMember;
use App\Repositories\User\WorkspaceMemberRepository;
use App\Support\Permissions;
use Illuminate\Http\Request;

/**
 * What someone may do in a business (Doc 12 section 2, steps 3 and 4 - the
 * package and the app are checked before, by `workspace` and `app`): its
 * owner everything, everywhere; a member what their role allows, while the
 * membership and the role are both active, at the locations they may use;
 * anyone else nothing.
 *
 * Worked out once per request for each business and person.
 */
class WorkspaceAccess
{
    public const REFUSED = 'Your role does not allow this.';

    public function __construct(
        protected WorkspaceMemberRepository $members,
    ) {}

    /**
     * Permissions::ALL alone for the owner and a role with full access.
     *
     * @return list<string>
     */
    public function granted(User $user, Workspace $workspace): array
    {
        return $this->resolved($user, $workspace)['granted'];
    }

    /**
     * The locations $user may use in $workspace (Doc 12 section 20): null for
     * every one - the owner's, and a member's with All Locations - else the
     * ids of those chosen for them, active or not.
     *
     * @return list<int>|null
     */
    public function locationsOf(User $user, Workspace $workspace): ?array
    {
        return $this->resolved($user, $workspace)['locations'];
    }

    /** Whether $user holds $permissions - any one of them, when several. */
    public function allows(User $user, Workspace $workspace, string|array $permissions): bool
    {
        $granted = $this->granted($user, $workspace);

        return in_array(Permissions::ALL, $granted, true) || array_intersect((array) $permissions, $granted) !== [];
    }

    /** Whether the signed-in user may, in the business the request is for. */
    public function can(string|array $permissions): bool
    {
        $request   = app(Request::class);
        $workspace = $request->attributes->get('workspace');

        return $request->user() !== null && $workspace instanceof Workspace && $this->allows($request->user(), $workspace, $permissions);
    }

    /** As can(), refused with a 403 when not. */
    public function authorize(string|array $permissions): void
    {
        abort_unless($this->can($permissions), 403, self::REFUSED);
    }

    /**
     * As locationsOf(), for the signed-in user in the business the request is
     * for - none at all without them.
     *
     * @return list<int>|null
     */
    public function locations(): ?array
    {
        $request   = app(Request::class);
        $workspace = $request->attributes->get('workspace');

        return $request->user() !== null && $workspace instanceof Workspace ? $this->locationsOf($request->user(), $workspace) : [];
    }

    /** Whether the signed-in user may see $family's values: its records' amounts, prices and totals (Doc 12 section 14). */
    public function seesValues(string $family): bool
    {
        return $this->can("{$family}.values.view");
    }

    /** Whether the signed-in user may use location $id: see and make records there. */
    public function usesLocation(int $id): bool
    {
        $locations = $this->locations();

        return $locations === null || in_array($id, $locations, true);
    }

    /**
     * Of $types, those whose records the signed-in user may view: what a
     * list across them shows, so each of its rows opens.
     *
     * @param  list<DocumentType>  $types
     * @return list<DocumentType>
     */
    public function viewable(array $types): array
    {
        return array_values(array_filter($types, fn (DocumentType $type) => $this->can("{$type->permissionGroup()}.view")));
    }

    /** @return array{granted: list<string>, locations: list<int>|null} */
    private function resolved(User $user, Workspace $workspace): array
    {
        $request = app()->bound('request') ? app(Request::class) : null;
        $key     = "access:{$workspace->id}:{$user->id}";

        if ($request?->attributes->has($key)) {
            return $request->attributes->get($key);
        }

        $resolved = $this->resolve($user, $workspace);
        $request?->attributes->set($key, $resolved);

        return $resolved;
    }

    /** @return array{granted: list<string>, locations: list<int>|null} */
    private function resolve(User $user, Workspace $workspace): array
    {
        if ($workspace->isOwnedBy($user)) {
            return ['granted' => [Permissions::ALL], 'locations' => null];
        }

        $member = $this->members->activeFor($workspace, $user);

        return [
            'granted'   => $member?->role?->is_active ? $member->role->granted() : [],
            'locations' => match (true) {
                $member === null                                                 => [],
                $member->location_access === WorkspaceMember::SELECTED_LOCATIONS => $member->locations->map(fn ($location) => (int) $location->id)->values()->all(),
                default                                                          => null,
            },
        ];
    }
}
