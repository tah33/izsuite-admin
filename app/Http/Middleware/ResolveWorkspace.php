<?php

namespace App\Http\Middleware;

use App\Repositories\User\WorkspaceRepository;
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
 * none, and the endpoints that need one say so - or, behind
 * `workspace:required` (a business's locations, staff, settings), are never
 * reached: the answer is a 404 that says to create one first.
 *
 * Read it back with $request->attributes->get('workspace').
 */
class ResolveWorkspace
{
    public const HEADER = 'X-Workspace-Id';

    public function __construct(
        protected WorkspaceRepository $workspaceRepository,
    ) {}

    public function handle(Request $request, Closure $next, ?string $need = null): Response
    {
        $id = (string) $request->header(self::HEADER, '');

        if ($id === '') {
            $workspace = $this->workspaceRepository->oldestForUserWithPackage($request->user());
        } else {
            $workspace = ctype_digit($id) ? $this->workspaceRepository->findForUserWithPackage($request->user(), (int) $id) : null;

            abort_if($workspace === null, 404, 'Workspace not found.');
        }

        abort_if($workspace === null && $need === 'required', 404, 'Create a workspace first.');

        $request->attributes->set('workspace', $workspace);

        return $next($request);
    }
}
