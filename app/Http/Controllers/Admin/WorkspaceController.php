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
     * Put the business on another plan. Moving down turns off - or moves to
     * Lite - what the new plan does not include; moving up turns nothing on.
     */
    public function updatePackage(UpdateWorkspacePackageRequest $request, int $id)
    {
        try {
            $workspace = $this->workspaceService->findOrFail($id);

            $package = $this->workspaceService->changePackage($workspace, (int) $request->validated('package_id'), $request->user());

            return $this->adminSuccess($request, "\"{$workspace->name}\" is now on {$package->name}.", url()->previous());

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }
}
