<?php

namespace App\Services\User\Apps;

use App\Enums\Edition;
use App\Models\Frontend\MenuItem;
use App\Models\User\User;
use App\Models\User\Workspace;
use App\Models\User\WorkspaceApp;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * The business sidebar for one user (GET /api/v1/navigation), and the state of
 * every app family the business has used. Nuxt draws it as it comes, with no
 * per-app code.
 *
 *   1. Core and bottom items (no family).
 *   2. One entry per active app family, named and placed by its app.
 *   3. Inside each, the family's items whose minimum edition the active edition
 *      covers.
 *   4. Less the items the user may not see, or whose Show if rule fails.
 *   5. Nested by parent_id.
 *
 * Cached under the business's nav_version and the catalog version, so a change
 * to either builds a fresh one - nothing is ever cleared by hand.
 */
class NavigationBuilder
{
    public function __construct(
        private ShowIfRules $rules,
    ) {}

    public function for(?Workspace $workspace, User $user): array
    {
        $key = sprintf(
            'nav:%s:%d:%d:%s',
            $workspace?->id ?? 'none',
            $user->id,
            $workspace?->nav_version ?? 0,
            CatalogVersion::current(),
        );

        return Cache::remember($key, now()->addDay(), fn () => $this->build($workspace, $user));
    }

    private function build(?Workspace $workspace, User $user): array
    {
        $apps = $workspace ? WorkspaceApps::for($workspace) : null;
        $on   = $apps?->active() ?? collect();

        $items = MenuItem::where('is_active', true)->orderBy('sort_order')->orderBy('id')->get()
            ->filter(fn (MenuItem $item) => $item->family_key === null
                || ($on->has($item->family_key)
                    && $on[$item->family_key]->app->edition->covers($item->min_edition ?? Edition::Lite)))
            ->filter(fn (MenuItem $item) => $this->userMay($user, $workspace, $item->permission_key))
            ->filter(fn (MenuItem $item) => ! $item->show_if || $this->rules->passes($item->show_if, $workspace));

        return [
            'workspace' => $workspace ? [
                'id'      => $workspace->id,
                'name'    => $workspace->name,
                'package' => $workspace->package ? [
                    'key'  => $workspace->package->key,
                    'name' => $workspace->package->name,
                ] : null,
            ] : null,

            // An object even when empty, so the client always gets a map.
            'apps' => (object) ($apps?->all()->map(fn (WorkspaceApp $row) => [
                'app_key'     => $row->app->app_key,
                'name'        => $row->app->name,
                'edition'     => $row->app->edition->value,
                'status'      => $row->status,
                'has_history' => true,
            ])->all() ?? []),

            'menu' => [
                'core'   => $this->tree($items->where('section', MenuItem::SECTION_CORE)),
                'apps'   => $this->appEntries($on, $items->where('section', MenuItem::SECTION_APPS)),
                'bottom' => $this->tree($items->where('section', MenuItem::SECTION_BOTTOM)),
            ],
        ];
    }

    /**
     * Whether $user may see an item that needs $permission.
     *
     * Only the owner can open a business for now, and the owner may do
     * everything. When staff can sign in, their roles are checked here.
     */
    private function userMay(User $user, ?Workspace $workspace, ?string $permission): bool
    {
        return $permission === null || (bool) $workspace?->isOwnedBy($user);
    }

    /** One entry per active family, in the catalog's order, holding its items. */
    private function appEntries(Collection $on, Collection $items): array
    {
        return $on
            ->sortBy(fn (WorkspaceApp $row) => [$row->app->sort_order, $row->app->name])
            ->map(fn (WorkspaceApp $row) => [
                'family'   => $row->family_key,
                'label'    => $row->app->name,
                'icon'     => $row->app->icon,
                'edition'  => $row->app->edition->value,
                'children' => $this->tree($items->where('family_key', $row->family_key)),
            ])
            ->values()
            ->all();
    }

    private function tree(Collection $items, ?int $parentId = null): array
    {
        return $items
            ->filter(fn (MenuItem $item) => $item->parent_id === $parentId)
            ->map(fn (MenuItem $item) => array_filter([
                'label'    => $item->label,
                'icon'     => $item->icon,
                'route'    => $item->route,
                'children' => $this->tree($items, $item->id) ?: null,
            ], fn ($value) => $value !== null))
            ->values()
            ->all();
    }
}
