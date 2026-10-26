<?php

namespace App\Services\User\Apps;

use App\Models\Frontend\AppCategory;
use App\Models\Frontend\Application;
use App\Models\User\Workspace;
use App\Repositories\Admin\AppCategoryRepository;
use App\Repositories\Admin\AppRepository;
use Illuminate\Support\Collection;

/**
 * The Apps Center for one business (GET /api/v1/apps-center): every listed app
 * family by category, with the state of each edition. Laravel works the states
 * out; Nuxt only renders them.
 *
 *   active            this edition is on                      → Turn off
 *   available         in the plan, family off                 → Turn on
 *   upgrade           in the plan, a lower edition is on      → Upgrade (replaces it)
 *   included          a higher edition of the family is on    → nothing to do
 *   needs_apps        something it needs is off               → turn those on first
 *   upgrade_required  not in this business's plan             → see plans
 *   coming_soon       listed but not available yet
 */
class AppsCenter
{
    public function __construct(
        private AppDependencies $dependencies,
        private DeactivateApp $deactivate,
        private AppRepository $catalog,
        private AppCategoryRepository $categories,
    ) {}

    public function for(Workspace $workspace): array
    {
        $apps     = WorkspaceApps::for($workspace);
        $families = $this->catalog->activeWithDependencies()->groupBy('family_key');

        return [
            'workspace' => [
                'id'      => $workspace->id,
                'name'    => $workspace->name,
                'package' => $workspace->package ? [
                    'key'  => $workspace->package->key,
                    'name' => $workspace->package->name,
                ] : null,
            ],
            'categories' => $this->categories->activeInOrder()
                ->map(fn (AppCategory $category) => [
                    'id'       => $category->id,
                    'name'     => $category->name,
                    'icon'     => $category->icon,
                    'families' => $families
                        ->filter(fn (Collection $editions) => $this->primary($editions)->category_id === $category->id)
                        ->sortBy(fn (Collection $editions) => [$this->primary($editions)->sort_order, $this->primary($editions)->name])
                        ->map(fn (Collection $editions, string $family) => $this->family($workspace, $apps, $family, $editions))
                        ->values()
                        ->all(),
                ])
                ->filter(fn (array $category) => $category['families'] !== [])
                ->values()
                ->all(),
        ];
    }

    /** The edition that names the family's card: Full, else the only one. */
    private function primary(Collection $editions): Application
    {
        return $editions->sortByDesc(fn (Application $app) => $app->edition->rank())->first();
    }

    private function family(Workspace $workspace, WorkspaceApps $apps, string $family, Collection $editions): array
    {
        $primary = $this->primary($editions);
        $row     = $apps->row($family);
        $on      = $row?->isActive() ? $row->app : null;

        return [
            'family_key'  => $family,
            'name'        => $primary->name,
            'icon'        => $primary->icon,
            'description' => $primary->description,
            'active'      => $on ? ['app_key' => $on->app_key, 'name' => $on->name, 'edition' => $on->edition->value] : null,
            'has_history' => $row !== null,
            'editions'    => $editions
                ->sortBy(fn (Application $app) => $app->edition->rank())
                ->map(fn (Application $app) => $this->edition($workspace, $apps, $app, $on))
                ->values()
                ->all(),
        ];
    }

    private function edition(Workspace $workspace, WorkspaceApps $apps, Application $app, ?Application $on): array
    {
        $missing = $this->dependencies->missing($apps, $app);

        $state = match (true) {
            $app->status === 'coming_soon'                                => 'coming_soon',
            $on?->id === $app->id                                         => 'active',
            $on !== null && $on->edition->rank() > $app->edition->rank() => 'included',
            ! $workspace->package?->allows($app)                          => 'upgrade_required',
            $missing->isNotEmpty()                                        => 'needs_apps',
            $on !== null                                                  => 'upgrade',
            default                                                       => 'available',
        };

        $result = [
            'app_key'       => $app->app_key,
            'name'          => $app->name,
            'edition'       => $app->edition->value,
            'edition_label' => $app->editionLabel(),
            'state'         => $state,
            'requires'      => $app->dependencies
                ->map(fn ($need) => $this->dependencies->familyName($need->requires_family, $need->min_edition))
                ->values()
                ->all(),
            'missing'       => $state === 'needs_apps' ? $missing->all() : [],
        ];

        if ($state === 'upgrade') {
            $result['replaces'] = $on->name;
        }

        // What turning it off would do, so the client can say so before asking.
        if ($state === 'active') {
            $fallback = $this->deactivate->fallbackFor($workspace, $app);

            $result['falls_back_to'] = $fallback?->name;
            $result['required_by']   = $this->dependencies->brokenBy($apps, $app->family_key, $fallback?->edition)->all();
        }

        return $result;
    }
}
