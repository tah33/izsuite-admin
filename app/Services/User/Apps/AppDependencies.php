<?php

namespace App\Services\User\Apps;

use App\Enums\Edition;
use App\Models\Frontend\AppDependency;
use App\Models\Frontend\Application;
use App\Models\User\WorkspaceApp;
use App\Repositories\Admin\AppRepository;
use Illuminate\Support\Collection;

/**
 * What apps need from each other (app_dependencies), checked against one
 * business, or against the catalog itself when an admin edits it.
 */
class AppDependencies
{
    public function __construct(
        private AppRepository $apps,
    ) {}

    /**
     * Names of what $app needs that is off, or on below the edition it needs.
     *
     * @return Collection<int, string>
     */
    public function missing(WorkspaceApps $apps, Application $app): Collection
    {
        return $app->dependencies
            ->reject(fn (AppDependency $need) => $apps->has($need->requires_family, $need->min_edition))
            ->map(fn (AppDependency $need) => $this->familyName($need->requires_family, $need->min_edition))
            ->values();
    }

    /**
     * Names of the active apps that would lose something they need if $family
     * dropped to $remaining - an edition, or null for off altogether.
     *
     * @return Collection<int, string>
     */
    public function brokenBy(WorkspaceApps $apps, string $family, ?Edition $remaining): Collection
    {
        return $apps->active()
            ->reject(fn (WorkspaceApp $row) => $row->family_key === $family)
            ->filter(fn (WorkspaceApp $row) => $row->app->dependencies->contains(
                fn (AppDependency $need) => $need->requires_family === $family
                    && ($remaining === null || ! $remaining->covers($need->min_edition))
            ))
            ->map(fn (WorkspaceApp $row) => $row->app->name)
            ->values();
    }

    /**
     * Whether $app requiring $family would close a loop: $family already
     * needs - directly or through others - the family $app belongs to.
     */
    public function wouldLoop(Application $app, string $family): bool
    {
        $needs = $this->apps->requirementPairs()
            ->groupBy('family')
            ->map(fn (Collection $rows) => $rows->pluck('requires')->unique()->all());

        $seen  = [];
        $queue = [$family];

        while ($queue !== []) {
            $current = array_shift($queue);

            if ($current === $app->family_key) {
                return true;
            }

            if (isset($seen[$current])) {
                continue;
            }

            $seen[$current] = true;
            array_push($queue, ...($needs[$current] ?? []));
        }

        return false;
    }

    /** What to call a requirement: its edition's name, else any of the family's. */
    public function familyName(string $family, Edition $min): string
    {
        return $this->apps->editionName($family, $min)
            ?? $this->apps->anyEditionName($family)
            ?? $family;
    }
}
