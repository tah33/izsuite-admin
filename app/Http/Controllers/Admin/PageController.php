<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StorePageRequest;
use App\Http\Requests\Admin\UpdatePageRequest;
use App\Services\Shared\ActivityLogService;
use App\Services\Admin\PageService;

class PageController extends Controller
{
    public function __construct(
        protected PageService $pageService,
    ) {}

    /**
     * List all pages.
     */
    public function index()
    {
        try {
            $pages = $this->pageService->getAllPaginated();

            return view('admin.pages.index', compact('pages'));

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
            return view('admin.pages.create');

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    /**
     * Store new page.
     */
    public function store(StorePageRequest $request)
    {
        try {
            $validated                   = $request->validated();

            $validated['show_in_footer'] = $request->boolean('show_in_footer');
            $validated['sort_order']     = $validated['sort_order'] ?? 0;

            $page                        = $this->pageService->create($validated);

            ActivityLogService::record('created', "Created page \"{$page->title}\"", $page);

            return redirect()->route('admin.pages.index')
                ->with('success', 'Page created successfully.');

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    /**
     * Edit page.
     */
    public function edit(int $id)
    {
        try {
            $page = $this->pageService->find($id);

            if (! $page) {
                abort(404);
            }

            return view('admin.pages.edit', compact('page'));

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    /**
     * Update page.
     */
    public function update(UpdatePageRequest $request, int $id)
    {
        try {
            $page                        = $this->pageService->find($id);

            if (! $page) {
                abort(404);
            }

            $validated                   = $request->validated();

            $validated['show_in_footer'] = $request->boolean('show_in_footer');
            $validated['sort_order']     = $validated['sort_order'] ?? 0;

            $this->pageService->update($page, $validated);

            ActivityLogService::record('updated', "Updated page \"{$page->title}\"", $page);

            return redirect()->route('admin.pages.index')
                ->with('success', 'Page updated successfully.');

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    /**
     * Delete page.
     */
    public function destroy(int $id)
    {
        try {
            $page  = $this->pageService->find($id);

            if (! $page) {
                abort(404);
            }

            $title = $page->title;
            $this->pageService->delete($page);

            ActivityLogService::record('deleted', "Deleted page \"{$title}\"");

            return redirect()->route('admin.pages.index')
                ->with('success', 'Page deleted successfully.');

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }
}
