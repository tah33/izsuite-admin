<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Edition;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreAppDependencyRequest;
use App\Http\Requests\Admin\StoreAppRequest;
use App\Models\Frontend\AppCategory;
use App\Models\Frontend\AppDependency;
use App\Models\Frontend\Application;
use App\Models\User\WorkspaceApp;
use App\Services\Admin\AppCatalogRules;
use App\Services\Admin\AppService;
use App\Services\Shared\ActivityLogService;
use App\Services\Support\ImageService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AppController extends Controller
{
    public function __construct(
        protected AppService $appService,
        protected ImageService $imageService,
        protected AppCatalogRules $rules,
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
            $hasFull = Application::where('edition', Edition::Full)->pluck('family_key')->flip();

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
            $app  = new Application(['status' => 'available', 'is_active' => true, 'edition' => Edition::Full]);
            $lite = $request->integer('full_of') ? Application::find($request->integer('full_of')) : null;

            if ($lite && $lite->edition === Edition::Lite) {
                $app->fill([
                    'name'        => trim(Str::replaceLast(' Lite', '', $lite->name)),
                    'family_key'  => $lite->family_key,
                    'app_key'     => $lite->family_key,
                    'category_id' => $lite->category_id,
                    'icon'        => $lite->icon,
                    'sort_order'  => $lite->sort_order,
                    'description' => $lite->description,
                ]);
            }

            return view('admin.apps.create', ['app' => $app, 'categories' => $this->categories()]);

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
            $validated = $request->validated();

            if ($request->hasFile('image')) {
                $validated['logo_url'] = $this->imageService->storePublic($request->file('image'), 'apps');
            }
            unset($validated['image']);

            $app = $this->appService->create($validated);

            ActivityLogService::record('created', "Created app \"{$app->name}\"", $app);

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

            $app->load(['dependencies', 'packages']);

            // Every other family, by its Full name (else Lite), for "Requires".
            $families = Application::where('family_key', '!=', $app->family_key)
                ->orderBy('name')
                ->get()
                ->groupBy('family_key')
                ->map(fn ($editions) => $editions->sortByDesc(fn (Application $e) => $e->edition->rank())->first()->name)
                ->sort();

            return view('admin.apps.edit', [
                'app'        => $app,
                'categories' => $this->categories(),
                'families'   => $families,
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

            $validated = $request->validated();

            if ($request->hasFile('image')) {
                $validated['logo_url'] = $this->imageService->storePublic($request->file('image'), 'apps', $app->logo_url);
            }
            unset($validated['image']);

            $this->appService->update($app, $validated);

            ActivityLogService::record('updated', "Updated app \"{$app->name}\"", $app);

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

            if (WorkspaceApp::where('app_id', $app->id)->exists()) {
                return $this->adminFailure(request(), 'Businesses have used this app, and its records point at it. Set it inactive instead.', 422);
            }

            $name = $app->name;
            $this->imageService->deletePublic($app->logo_url);
            $this->appService->delete($app);

            ActivityLogService::record('deleted', "Deleted app \"{$name}\"");

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

            if ($problem = $this->rules->dependencyProblem($app, $family, $min)) {
                return $this->adminFailure($request, $problem, 422);
            }

            $app->dependencies()->updateOrCreate(['requires_family' => $family], ['min_edition' => $min]);

            ActivityLogService::record('updated', "Set \"{$app->name}\" to require {$family}", $app);

            return $this->adminSuccess($request, 'Requirement saved.', route('admin.apps.edit', $app->id));

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function destroyDependency(Request $request, int $id, int $dependencyId)
    {
        try {
            $dependency = AppDependency::where('app_id', $id)->find($dependencyId);

            if (! $dependency) {
                abort(404);
            }

            $dependency->delete();

            return $this->adminSuccess($request, 'Requirement removed.', route('admin.apps.edit', $id));

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    /** Active categories, in the Apps Center's order: id => name. */
    private function categories()
    {
        return AppCategory::where('is_active', true)->orderBy('sort_order')->orderBy('name')->pluck('name', 'id');
    }
}
