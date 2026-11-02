<?php

namespace App\Http\Middleware;

use App\Services\Shared\LocationAccess;
use App\Services\Shared\WorkspaceAccess;
use App\Support\Permissions;
use App\Support\RoutePermissions;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guards a business's API routes by role and location (Doc 12 section 2,
 * steps 3 and 4). Hiding a button is not security; this is.
 *
 * The route's permission comes from RoutePermissions. The owner and a role
 * with full access pass every route; anyone else needs what the route needs,
 * and is refused a route that is not listed. MEMBER routes are open to anyone
 * `workspace` let in. Then a route's record at a location the user may not
 * use is refused, whatever the role (LocationAccess).
 *
 * Runs after `workspace`, which picks the business and lets in only its
 * owner and its active members, and after `app`.
 */
class EnsurePermission
{
    public function __construct(
        protected WorkspaceAccess $access,
        protected LocationAccess $locationAccess,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $workspace = $request->attributes->get('workspace');

        // No business, nothing to guard: `workspace` answers for that.
        if ($workspace === null) {
            return $next($request);
        }

        $granted = $this->access->granted($request->user(), $workspace);

        if (! in_array(Permissions::ALL, $granted, true)) {
            $needs = RoutePermissions::for($request);

            abort_unless(
                $needs === RoutePermissions::MEMBER || ($needs !== null && array_intersect((array) $needs, $granted) !== []),
                403,
                WorkspaceAccess::REFUSED,
            );
        }

        $this->locationAccess->authorizeRoute($request, $workspace);

        return $next($request);
    }
}
