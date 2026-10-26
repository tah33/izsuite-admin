<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateWorkspacePackageRequest;
use App\Services\Admin\WorkspaceService;
use Illuminate\Http\Request;

/**
 * Every business workspace: its owner, its plan and the apps it has on, and
 * moving it to another plan. Until package checkout exists, this is how a
 * business is upgraded or downgraded.
 */
class WorkspaceController extends Controller
{
    public function __construct(
        protected WorkspaceService $workspaceService,
    ) {}

    public function index(Request $request)
    {
        try {
            $workspaces = $this->workspaceService->paginate(
                trim((string) $request->query('search')),
                $request->query('package'),
                (int) $request->query('per_page', 15),
            );

            $packages = $this->workspaceService->packages();

            return view('admin.workspaces.index', compact('workspaces', 'packages'));

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

            return $this->adminSuccess($request, "\"{$workspace->name}\" is now on {$package->name}.", url()->previous());

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }
}
