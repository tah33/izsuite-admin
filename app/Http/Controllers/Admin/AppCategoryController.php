<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreAppCategoryRequest;
use App\Models\Frontend\AppCategory;
use App\Services\Admin\AppCategoryService;
use Illuminate\Http\Request;

class AppCategoryController extends Controller
{
    public function __construct(
        protected AppCategoryService $appCategoryService,
    ) {}

    /**
     * List all app categories.
     */
    public function index(Request $request)
    {
        try {
            $categories = $this->appCategoryService->getFilteredCategories(
                $request->query('search'),
                (int) $request->query('per_page', 15),
            );

            return view('admin.app-categories.index', compact('categories'));

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
            $appCategory = new AppCategory();

            return view('admin.app-categories.create', compact('appCategory'));

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    /**
     * Store a new app category.
     */
    public function store(StoreAppCategoryRequest $request)
    {
        try {
            $validated = $request->validated();
            $validated['is_active'] = $request->boolean('is_active');

            $this->appCategoryService->create($validated);

            return $this->adminSuccess(
                $request,
                'App category created successfully.',
                route('admin.app-categories.index')
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
            $appCategory = $this->appCategoryService->find($id);

            if (! $appCategory) {
                abort(404);
            }

            return view('admin.app-categories.edit', compact('appCategory'));

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    /**
     * Update an app category.
     */
    public function update(StoreAppCategoryRequest $request, int $id)
    {
        try {
            $appCategory = $this->appCategoryService->find($id);

            if (! $appCategory) {
                abort(404);
            }

            $validated = $request->validated();
            $validated['is_active'] = $request->boolean('is_active');

            $this->appCategoryService->update($appCategory, $validated);

            return $this->adminSuccess(
                $request,
                'App category updated successfully.',
                route('admin.app-categories.index')
            );

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    /**
     * Toggle the active state of an app category.
     */
    public function toggleActive(Request $request, int $id)
    {
        try {
            $appCategory = $this->appCategoryService->find($id);

            if (! $appCategory) {
                abort(404);
            }

            $this->appCategoryService->toggleActive($appCategory);

            $state = $appCategory->is_active ? 'activated' : 'deactivated';

            return $this->adminSuccess(
                $request,
                "\"{$appCategory->name}\" has been {$state}.",
                route('admin.app-categories.index')
            );

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    /**
     * Remove the specified app category from storage.
     */
    public function destroy(int $id)
    {
        try {
            $appCategory = $this->appCategoryService->find($id);

            if (! $appCategory) {
                abort(404);
            }

            if ($problem = $this->appCategoryService->deletionProblem($appCategory)) {
                return $this->adminFailure(request(), $problem, 422);
            }

            $this->appCategoryService->delete($appCategory);

            return $this->adminSuccess(
                request(),
                'App category deleted successfully.',
                route('admin.app-categories.index')
            );

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }
}
