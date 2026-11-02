<?php

namespace App\Http\Middleware;

use App\Services\Shared\WorkspaceAccess;
use App\Support\Permissions;
use App\Support\RoutePermissions;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guards a business's API routes by role (Doc 12 section 2, step 3). Hiding a
 * button is not security; this is.
 *
 * The route's permission comes from RoutePermissions. The owner and a role
 * with full access pass every route; anyone else needs what the route needs,
 * and is refused a route that is not listed. MEMBER routes are open to anyone
 * `workspace` let in.
 *
 * Runs after `workspace`, which picks the business and lets in only its
 * owner and its active members, and after `app`.
 */
class EnsurePermission
{
    public function __construct(
        protected WorkspaceAccess $access,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $workspace = $request->attributes->get('workspace');

        // No business, nothing to guard: `workspace` answers for that.
        if ($workspace === null) {
            return $next($request);
        }

        $granted = $this->access->granted($request->user(), $workspace);

        if (in_array(Permissions::ALL, $granted, true)) {
            return $next($request);
        }

        $needs = RoutePermissions::for($request);

        abort_unless(
            $needs === RoutePermissions::MEMBER || ($needs !== null && array_intersect((array) $needs, $granted) !== []),
            403,
            WorkspaceAccess::REFUSED,
        );

        return $next($request);
    }
}
