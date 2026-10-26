<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ReorderMenuItemsRequest;
use App\Http\Requests\Admin\StoreMenuItemRequest;
use App\Models\Frontend\MenuItem;
use App\Services\Admin\MenuItemService;
use Illuminate\Http\Request;

/**
 * The Menu Builder: the items of the business sidebar - Core, each app family,
 * and Bottom. An app's own entry (name, icon, place) comes from the Apps
 * screen; this manages what is inside it.
 *
 * Every save changes the catalog version, so every business sidebar is built
 * afresh on its next request. A new item needs no deploy - but the page it
 * points at must exist in Nuxt.
 */
class MenuItemController extends Controller
{
    public function __construct(
        protected MenuItemService $menuItemService,
    ) {}

    public function index()
    {
        try {
            return view('admin.menu-items.index', $this->menuItemService->lists());

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function create(Request $request)
    {
        try {
            $item = $this->menuItemService->newItem($request->query('section', MenuItem::SECTION_CORE), $request->query('family'));

            return view('admin.menu-items.create', $this->menuItemService->formData($item));

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function store(StoreMenuItemRequest $request)
    {
        try {
            $this->menuItemService->create($request->validated());

            return $this->adminSuccess($request, 'Menu item added.', route('admin.menu-items.index'));

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function edit(int $id)
    {
        try {
            return view('admin.menu-items.edit', $this->menuItemService->formData($this->menuItemService->findOrFail($id)));

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function update(StoreMenuItemRequest $request, int $id)
    {
        try {
            $this->menuItemService->update($this->menuItemService->findOrFail($id), $request->validated());

            return $this->adminSuccess($request, 'Menu item updated.', route('admin.menu-items.index'));

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function destroy(int $id)
    {
        try {
            $this->menuItemService->delete($this->menuItemService->findOrFail($id));

            return $this->adminSuccess(request(), 'Menu item deleted.', route('admin.menu-items.index'));

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    /**
     * Save a dragged order: the ids of one list, top to bottom.
     */
    public function reorder(ReorderMenuItemsRequest $request)
    {
        try {
            $this->menuItemService->reorder($request->validated('ids'));

            return $this->adminSuccess($request, 'Order saved.');

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }
}
