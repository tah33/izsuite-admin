<?php

namespace App\Services\Admin;

use App\Enums\Edition;
use App\Models\Frontend\MenuItem;
use App\Repositories\Admin\MenuItemRepository;
use App\Services\Shared\ActivityLogService;
use App\Services\User\Apps\CatalogVersion;
use App\Services\User\Apps\ShowIfRules;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The Menu Builder: the items of the business sidebar - Core, each app family,
 * and Bottom. An app's own entry (name, icon, place) comes from the Apps
 * screen; this manages what is inside it.
 */
class MenuItemService
{
    public function __construct(
        protected MenuItemRepository $menuItemRepository,
        protected AppService $appService,
        protected ShowIfRules $rules,
    ) {}

    /** The Menu Builder's lists: Core, Bottom and each app family's, each as a tree. */
    public function lists(): array
    {
        $items = $this->menuItemRepository->allOrdered();

        return [
            'core'      => $this->tree($items->where('section', MenuItem::SECTION_CORE)),
            'bottom'    => $this->tree($items->where('section', MenuItem::SECTION_BOTTOM)),
            'families'  => $this->appService->catalogFamilyNames()->map(fn (string $name, string $family) => [
                'key'   => $family,
                'name'  => $name,
                'items' => $this->tree($items->where('section', MenuItem::SECTION_APPS)->where('family_key', $family)),
            ]),
            'showIf'    => $this->rules->options(),
            // The items with others under them, whose delete takes those too.
            'parentIds' => $items->pluck('parent_id')->filter()->unique()->values()->all(),
        ];
    }

    /** A new item for the create form: in $section - or $family's - and last. */
    public function newItem(string $section, ?string $family): MenuItem
    {
        return new MenuItem([
            'section'     => $section,
            'family_key'  => $family,
            'min_edition' => $family ? Edition::Lite : null,
            'is_active'   => true,
            'sort_order'  => $this->menuItemRepository->maxSortOrder() + 10,
        ]);
    }

    public function findOrFail(int $id): MenuItem
    {
        return $this->menuItemRepository->findOrFail($id);
    }

    /** What the create and edit forms offer for $item. */
    public function formData(MenuItem $item): array
    {
        return [
            'item'     => $item,
            'families' => $this->appService->catalogFamilyNames(),
            'parents'  => $this->menuItemRepository->possibleParents($item),
            'showIf'   => $this->rules->options(),
        ];
    }

    public function create(array $data): MenuItem
    {
        $item = $this->menuItemRepository->create($data);

        ActivityLogService::record('created', "Added menu item \"{$item->label}\"", $item);

        return $item;
    }

    public function update(MenuItem $item, array $data): MenuItem
    {
        $this->menuItemRepository->update($item, $data);

        ActivityLogService::record('updated', "Updated menu item \"{$item->label}\"", $item);

        return $item;
    }

    public function delete(MenuItem $item): void
    {
        $label = $item->label;
        $this->menuItemRepository->delete($item);

        ActivityLogService::record('deleted', "Deleted menu item \"{$label}\"");
    }

    /** Saves a dragged order: the ids of one list, top to bottom. */
    public function reorder(array $ids): void
    {
        DB::transaction(function () use ($ids) {
            foreach (array_values($ids) as $position => $id) {
                $this->menuItemRepository->setSortOrder($id, ($position + 1) * 10);
            }
        });

        // Query updates skip model events, so the version is changed here.
        CatalogVersion::bump();
    }

    private function tree(Collection $items): Collection
    {
        return $items->whereNull('parent_id')->values()->map(fn (MenuItem $item) => [
            'item'     => $item,
            'children' => $items->where('parent_id', $item->id)->values(),
        ]);
    }
}
