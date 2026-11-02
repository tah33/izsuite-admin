<?php

namespace App\Services\Shared;

use App\Models\User\User;
use App\Models\User\Workspace;
use App\Repositories\User\WorkspaceMemberRepository;
use App\Support\Permissions;
use Illuminate\Http\Request;

/**
 * What someone may do in a business (Doc 12 section 2, step 3 - the package
 * and the app are checked before, by `workspace` and `app`): its owner
 * everything; a member what their role allows, while the membership and the
 * role are both active; anyone else nothing.
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
        $request = app()->bound('request') ? app(Request::class) : null;
        $key     = "granted:{$workspace->id}:{$user->id}";

        if ($request?->attributes->has($key)) {
            return $request->attributes->get($key);
        }

        $granted = $this->resolve($user, $workspace);
        $request?->attributes->set($key, $granted);

        return $granted;
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

    /** @return list<string> */
    private function resolve(User $user, Workspace $workspace): array
    {
        if ($workspace->isOwnedBy($user)) {
            return [Permissions::ALL];
        }

        $member = $this->members->activeFor($workspace, $user);

        return $member?->role?->is_active ? $member->role->granted() : [];
    }
}
