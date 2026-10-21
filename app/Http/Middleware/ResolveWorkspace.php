<?php

namespace App\Http\Middleware;

use App\Models\User\Workspace;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Which business a request is for: the X-Workspace-Id header - the workspace
 * picked in the frontend's top bar - or, without one, the signed-in user's
 * oldest workspace.
 *
 * Only the user's own workspaces count: any other id is a 404, so a caller
 * cannot tell "not yours" from "does not exist". A user with no workspace gets
 * none, and the endpoints that need one say so.
 *
 * Read it back with $request->attributes->get('workspace').
 */
class ResolveWorkspace
{
    public const HEADER = 'X-Workspace-Id';

    public function handle(Request $request, Closure $next): Response
    {
        $id    = (string) $request->header(self::HEADER, '');
        $query = Workspace::where('user_id', $request->user()->id)->with('package');

        if ($id === '') {
            $workspace = $query->orderBy('id')->first();
        } else {
            $workspace = ctype_digit($id) ? $query->find((int) $id) : null;

            abort_if($workspace === null, 404, 'Workspace not found.');
        }

        $request->attributes->set('workspace', $workspace);

        return $next($request);
    }
}
