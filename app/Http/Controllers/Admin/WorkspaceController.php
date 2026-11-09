<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateWorkspacePackageRequest;
use App\Services\Admin\WorkspaceService;

/**
 * Moving a business to another plan, from its owner's page under Clients.
 * Until package checkout exists, this is how a business is upgraded or
 * downgraded.
 */
class WorkspaceController extends Controller
{
    public function __construct(
        protected WorkspaceService $workspaceService,
    ) {}

    /**
     * What moving the business to the plan picked would do, before it is
     * done (Doc 13 sections 13 and 14): the apps that go to Lite or off, and
     * its users, locations and storage against the new plan's limits.
     */
    public function reviewPackage(UpdateWorkspacePackageRequest $request, int $id)
    {
        try {
            return view('admin.workspaces.package', $this->workspaceService->review($id, (int) $request->validated('package_id')));

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    /**
     * Put the business on another plan. Moving down turns off - or moves to
     * Lite - what the new plan does not include; moving up turns nothing on.
     */
    public function updatePackage(UpdateWorkspacePackageRequest $request, int $id)
    {
        try {
            $workspace = $this->workspaceService->findOrFail($id);

            $package = $this->workspaceService->changePackage($workspace, (int) $request->validated('package_id'), $request->user());

            return $this->adminSuccess($request, "\"{$workspace->name}\" is now on {$package->name}.", route('admin.clients.show', $workspace->user_id));

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }
}
