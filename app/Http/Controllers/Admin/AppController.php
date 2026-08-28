<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreAppRequest;
use App\Models\Frontend\AppCategory;
use App\Models\Frontend\Application;
use App\Services\Shared\ActivityLogService;
use App\Services\Admin\AppService;
use App\Services\Support\ImageService;
use Illuminate\Http\Request;

class AppController extends Controller
{
    public function __construct(
        protected AppService $appService,
        protected ImageService $imageService,
    ) {}

    /**
     * List all apps.
     */
    public function index(Request $request)
    {
        try {
            $apps = $this->appService->getFilteredApps(
                $request->query('search'),
                (int) $request->query('per_page', 15),
            );

            return view('admin.apps.index', compact('apps'));

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    /**
     * Show create form.
     */
    public function create()
    {
        try {
            $app        = new Application();
            $categories = AppCategory::where('is_active', true)->orderBy('name')->pluck('name', 'name');

            return view('admin.apps.create', compact('app', 'categories'));

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
                route('admin.apps.index')
            );

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    /**
     * Show edit form.
     */
    public function edit(int $id)
    {
        try {
            $app = $this->appService->find($id);

            if (! $app) {
                abort(404);
            }

            $categories = AppCategory::where('is_active', true)->orderBy('name')->pluck('name', 'name');

            return view('admin.apps.edit', compact('app', 'categories'));

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
     * Remove the specified app from storage.
     */
    public function destroy(int $id)
    {
        try {
            $app = $this->appService->find($id);

            if (! $app) {
                abort(404);
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
}
