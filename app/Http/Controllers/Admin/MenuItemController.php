<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Edition;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreMenuItemRequest;
use App\Models\Frontend\Application;
use App\Models\Frontend\MenuItem;
use App\Services\Shared\ActivityLogService;
use App\Services\User\Apps\CatalogVersion;
use App\Services\User\Apps\ShowIfRules;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
        protected ShowIfRules $rules,
    ) {}

    public function index()
    {
        try {
            $items = MenuItem::orderBy('sort_order')->orderBy('id')->get();

            return view('admin.menu-items.index', [
                'core'     => $this->tree($items->where('section', MenuItem::SECTION_CORE)),
                'bottom'   => $this->tree($items->where('section', MenuItem::SECTION_BOTTOM)),
                'families' => $this->families()->map(fn (string $name, string $family) => [
                    'key'   => $family,
                    'name'  => $name,
                    'items' => $this->tree($items->where('section', MenuItem::SECTION_APPS)->where('family_key', $family)),
                ]),
                'showIf'   => $this->rules->options(),
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function create(Request $request)
    {
        try {
            $item = new MenuItem([
                'section'     => $request->query('section', MenuItem::SECTION_CORE),
                'family_key'  => $request->query('family'),
                'min_edition' => $request->query('family') ? Edition::Lite : null,
                'is_active'   => true,
                'sort_order'  => (int) MenuItem::max('sort_order') + 10,
            ]);

            return view('admin.menu-items.create', $this->formData($item));

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function store(StoreMenuItemRequest $request)
    {
        try {
            $item = MenuItem::create($request->validated());

            ActivityLogService::record('created', "Added menu item \"{$item->label}\"", $item);

            return $this->adminSuccess($request, 'Menu item added.', route('admin.menu-items.index'));

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function edit(int $id)
    {
        try {
            return view('admin.menu-items.edit', $this->formData(MenuItem::findOrFail($id)));

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function update(StoreMenuItemRequest $request, int $id)
    {
        try {
            $item = MenuItem::findOrFail($id);
            $item->update($request->validated());

            ActivityLogService::record('updated', "Updated menu item \"{$item->label}\"", $item);

            return $this->adminSuccess($request, 'Menu item updated.', route('admin.menu-items.index'));

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function destroy(int $id)
    {
        try {
            $item  = MenuItem::findOrFail($id);
            $label = $item->label;
            $item->delete();

            ActivityLogService::record('deleted', "Deleted menu item \"{$label}\"");

            return $this->adminSuccess(request(), 'Menu item deleted.', route('admin.menu-items.index'));

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    /**
     * Save a dragged order: the ids of one list, top to bottom.
     */
    public function reorder(Request $request)
    {
        try {
            $ids = $request->validate([
                'ids'   => ['required', 'array', 'min:1'],
                'ids.*' => ['integer', 'exists:menu_items,id'],
            ])['ids'];

            DB::transaction(function () use ($ids) {
                foreach (array_values($ids) as $position => $id) {
                    MenuItem::whereKey($id)->update(['sort_order' => ($position + 1) * 10]);
                }
            });

            // Query updates skip model events, so the version is changed here.
            CatalogVersion::bump();

            return $this->adminSuccess($request, 'Order saved.');

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    /** Every app family, by its Full edition's name (else Lite's), in catalog order. */
    private function families()
    {
        return Application::orderBy('sort_order')->orderBy('family_key')->get()
            ->groupBy('family_key')
            ->map(fn ($editions) => $editions->sortByDesc(fn (Application $e) => $e->edition->rank())->first()->name);
    }

    private function tree($items)
    {
        return $items->whereNull('parent_id')->values()->map(fn (MenuItem $item) => [
            'item'     => $item,
            'children' => $items->where('parent_id', $item->id)->values(),
        ]);
    }

    private function formData(MenuItem $item): array
    {
        return [
            'item'     => $item,
            'families' => $this->families(),
            'parents'  => MenuItem::whereNull('parent_id')->whereKeyNot($item->id ?? 0)->orderBy('section')->orderBy('sort_order')->get(),
            'showIf'   => $this->rules->options(),
        ];
    }
}
