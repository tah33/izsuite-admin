<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Billing\Package;
use App\Models\User\Workspace;
use App\Models\User\WorkspaceApp;
use App\Services\Shared\ActivityLogService;
use App\Services\User\Apps\SyncAppsWithPackage;
use Illuminate\Http\Request;

/**
 * Every business workspace: its owner, its plan and the apps it has on, and
 * moving it to another plan. Until package checkout exists, this is how a
 * business is upgraded or downgraded.
 */
class WorkspaceController extends Controller
{
    public function __construct(
        protected SyncAppsWithPackage $sync,
    ) {}

    public function index(Request $request)
    {
        try {
            $search  = trim((string) $request->query('search'));
            $package = $request->query('package');

            $workspaces = Workspace::query()
                ->with([
                    'user:id,first_name,last_name,email',
                    'package:id,key,name',
                    'workspaceApps' => fn ($q) => $q->where('status', WorkspaceApp::STATUS_ACTIVE)->with('app:id,name,edition,sort_order'),
                ])
                ->when($search !== '', fn ($q) => $q->where(fn ($q) => $q
                    ->where('name', 'like', "%{$search}%")
                    ->orWhereHas('user', fn ($u) => $u->where('email', 'like', "%{$search}%"))))
                ->when($package, fn ($q) => $q->whereHas('package', fn ($p) => $p->where('key', $package)))
                ->latest('id')
                ->paginate((int) $request->query('per_page', 15))
                ->withQueryString();

            $packages = Package::orderBy('rank')->get();

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
    public function updatePackage(Request $request, int $id)
    {
        try {
            $workspace = Workspace::with('package')->findOrFail($id);

            $validated = $request->validate([
                'package_id' => ['required', 'integer', 'exists:packages,id'],
            ]);

            $from    = $workspace->package?->name ?? 'no plan';
            $package = Package::findOrFail($validated['package_id']);

            $this->sync->changePackage($workspace, $package, $request->user());

            ActivityLogService::record('updated', "Moved workspace \"{$workspace->name}\" from {$from} to {$package->name}", $workspace);

            return $this->adminSuccess($request, "\"{$workspace->name}\" is now on {$package->name}.", url()->previous());

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }
}
