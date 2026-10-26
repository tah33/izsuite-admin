<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Edition;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreAppDependencyRequest;
use App\Http\Requests\Admin\StoreAppRequest;
use App\Services\Admin\AppService;
use Illuminate\Http\Request;

class AppController extends Controller
{
    public function __construct(
        protected AppService $appService,
    ) {}

    /**
     * List all apps, families together.
     */
    public function index(Request $request)
    {
        try {
            $apps = $this->appService->getFilteredApps(
                $request->query('search'),
                (int) $request->query('per_page', 15),
            );

            // Families that already have a Full edition, for "Add Full edition".
            $hasFull = $this->appService->familiesWithFull();

            return view('admin.apps.index', compact('apps', 'hasFull'));

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    /**
     * Show create form - blank, or for the Full edition of a Lite app
     * (?full_of={id}), which starts from the Lite app's family, category, icon
     * and order.
     */
    public function create(Request $request)
    {
        try {
            $app = $this->appService->newApp($request->integer('full_of') ?: null);

            return view('admin.apps.create', ['app' => $app, 'categories' => $this->appService->categoryNames()]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    /**
     * Store a new app.
     */
    public function store(StoreAppRequest $request)
    {
        try {
            $app = $this->appService->create($request->validated());

            return $this->adminSuccess(
                $request,
                'App created successfully.',
                route('admin.apps.edit', $app->id)
            );

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    /**
     * Show edit form, with the app's dependencies.
     */
    public function edit(int $id)
    {
        try {
            $app = $this->appService->find($id);

            if (! $app) {
                abort(404);
            }

            return view('admin.apps.edit', [
                'app'        => $this->appService->withRequirements($app),
                'categories' => $this->appService->categoryNames(),
                // Every other family, by its Full name (else Lite), for "Requires".
                'families'   => $this->appService->otherFamilyNames($app),
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    /**
     * Update an app.
     */
    public function update(StoreAppRequest $request, int $id)
    {
        try {
            $app = $this->appService->find($id);

            if (! $app) {
                abort(404);
            }

            $this->appService->update($app, $request->validated());

            return $this->adminSuccess(
                $request,
                'App updated successfully.',
                route('admin.apps.index')
            );

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    /**
     * Remove the specified app - only one no business has ever turned on.
     */
    public function destroy(int $id)
    {
        try {
            $app = $this->appService->find($id);

            if (! $app) {
                abort(404);
            }

            if ($problem = $this->appService->deletionProblem($app)) {
                return $this->adminFailure(request(), $problem, 422);
            }

            $this->appService->delete($app);

            return $this->adminSuccess(
                request(),
                'App deleted successfully.',
                route('admin.apps.index')
            );

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    /**
     * Add something the app needs turned on first.
     */
    public function storeDependency(StoreAppDependencyRequest $request, int $id)
    {
        try {
            $app = $this->appService->find($id);

            if (! $app) {
                abort(404);
            }

            $family = $request->validated('requires_family');
            $min    = Edition::from($request->validated('min_edition'));

            if ($problem = $this->appService->requirementProblem($app, $family, $min)) {
                return $this->adminFailure($request, $problem, 422);
            }

            $this->appService->saveRequirement($app, $family, $min);

            return $this->adminSuccess($request, 'Requirement saved.', route('admin.apps.edit', $app->id));

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function destroyDependency(Request $request, int $id, int $dependencyId)
    {
        try {
            if (! $this->appService->deleteRequirement($id, $dependencyId)) {
                abort(404);
            }

            return $this->adminSuccess($request, 'Requirement removed.', route('admin.apps.edit', $id));

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }
}
