<?php

namespace App\Http\Middleware;

use App\Repositories\User\WorkspaceRepository;
use App\Services\User\Workspaces\BusinessStatusService;
use App\Support\SupportAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Which business a request is for: the X-Workspace-Id header - the workspace
 * picked in the frontend's top bar - or, without one, the signed-in user's
 * oldest workspace.
 *
 * Only a business the user owns or is an active member of counts (Doc 12
 * section 4): any other id is a 404, so a caller cannot tell "not yours"
 * from "does not exist". What a member may do in it is for `access` to
 * say. A user with no workspace gets
 * none, and the endpoints that need one say so - or, behind
 * `workspace:required` (a business's locations, staff, settings), are never
 * reached: the answer is a 404 that says to create one first.
 *
 * The business's own status has a say too (Doc 15 section 15.6): nothing of
 * a suspended or closed one opens but its navigation, and one restricted or
 * under review is read, not changed (BusinessStatusService::refusal).
 *
 * Read it back with $request->attributes->get('workspace').
 */
class ResolveWorkspace
{
    public const HEADER = 'X-Workspace-Id';

    public function __construct(
        protected WorkspaceRepository $workspaceRepository,
        protected BusinessStatusService $statuses,
    ) {}

    public function handle(Request $request, Closure $next, ?string $need = null): Response
    {
        $id = (string) $request->header(self::HEADER, '');

        // A support session is for one business (Doc 15 section 15.22): that one, and no other the owner has.
        if ($session = SupportAccess::current($request)) {
            abort_if($id !== '' && $id !== (string) $session->workspace_id, 404, 'Workspace not found.');
            $id = (string) $session->workspace_id;
        }

        if ($id === '') {
            $workspace = $this->workspaceRepository->oldestForUserWithPackage($request->user());
        } else {
            $workspace = ctype_digit($id) ? $this->workspaceRepository->findForUserWithPackage($request->user(), (int) $id) : null;

            abort_if($workspace === null, 404, 'Workspace not found.');
        }

        abort_if($workspace === null && $need === 'required', 404, 'Create a workspace first.');

        $request->attributes->set('workspace', $workspace);

        if ($workspace && ($refusal = $this->statuses->refusal($request, $workspace))) {
            return $refusal;
        }

        return $next($request);
    }
}
