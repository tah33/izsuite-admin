<?php

namespace App\Services\Admin;

use App\Enums\Edition;
use App\Models\Frontend\AppDependency;
use App\Models\Frontend\Application;
use App\Repositories\Admin\AppRepository;
use App\Repositories\Billing\PackageRepository;
use App\Services\User\Apps\AppDependencies;

/**
 * The catalog's own rules, checked when an admin edits dependencies or plan
 * access:
 *   - an app cannot need its own family, nor an edition its family lacks;
 *   - needs cannot go round in a circle;
 *   - a plan cannot hold an app without what that app needs - Inventory in
 *     Standard needs Purchases, so some edition of Purchases good enough for it
 *     has to be in Standard too. Each plan has its own apps: a plan includes
 *     only the apps ticked for it.
 */
class AppCatalogRules
{
    public function __construct(
        private AppDependencies $dependencies,
        private AppRepository $appRepository,
        private PackageRepository $packageRepository,
    ) {}

    /** Why $app cannot need $family at $min - or null when it can. */
    public function dependencyProblem(Application $app, string $family, Edition $min): ?string
    {
        if ($family === $app->family_key) {
            return 'An app cannot require its own family.';
        }

        $editions = $this->appRepository->editionsOf($family);
        $enough   = $editions->filter(fn (Application $edition) => $edition->edition->covers($min));

        if ($enough->isEmpty()) {
            return "That family has no {$min->label()} edition.";
        }

        if ($this->dependencies->wouldLoop($app, $family)) {
            return "{$enough->first()->name} already needs {$app->name}, directly or through other apps, so this would go round in a circle.";
        }

        $access = $this->access();

        foreach ($access[$app->id] ?? [] as $packageId) {
            if (! $enough->contains(fn (Application $edition) => in_array($packageId, $access[$edition->id] ?? [], true))) {
                return "{$app->name} is in {$this->planName($packageId)}, but {$this->dependencies->familyName($family, $min)} is not in that plan. Change Package Access first.";
            }
        }

        return null;
    }

    /**
     * What would be wrong with plan access $access (app id => the package ids
     * of the plans it is in; an app left out is in no plan).
     *
     * @param  array<int, list<int>>  $access
     * @return list<string>
     */
    public function accessProblems(array $access): array
    {
        $apps     = $this->appRepository->allWithDependencies()->keyBy('id');
        $problems = [];

        foreach ($apps as $app) {
            /** @var AppDependency $need */
            foreach ($app->dependencies as $need) {
                $enough = $apps->filter(fn (Application $b) => $b->family_key === $need->requires_family && $b->edition->covers($need->min_edition));

                foreach ($access[$app->id] ?? [] as $packageId) {
                    if (! $enough->contains(fn (Application $b) => in_array($packageId, $access[$b->id] ?? [], true))) {
                        $needed     = $this->dependencies->familyName($need->requires_family, $need->min_edition);
                        $problems[] = "{$app->name} is in {$this->planName($packageId)}, but needs {$needed}, which is not in that plan.";
                    }
                }
            }
        }

        return $problems;
    }

    /** @return array<int, list<int>> app id => the package ids of the plans it is in */
    public function access(): array
    {
        return $this->packageRepository->accessByApp()
            ->map(fn ($ids) => array_map('intval', $ids))
            ->all();
    }

    private function planName(int $packageId): string
    {
        return $this->packageRepository->namesByRank()->get($packageId) ?? "plan {$packageId}";
    }
}
