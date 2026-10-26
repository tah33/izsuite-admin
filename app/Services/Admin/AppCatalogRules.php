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
 *     has to be in Standard or a lower plan.
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

        $lowest = $this->lowestRanks();
        $rank   = $lowest[$app->id] ?? null;

        if ($rank === null) {
            return null;
        }

        $best = $enough->map(fn (Application $edition) => $lowest[$edition->id] ?? null)->filter(fn ($r) => $r !== null)->min();

        if ($best === null || $best > $rank) {
            return "{$app->name} is in {$this->planName($rank)}, but {$this->dependencies->familyName($family, $min)} is not in that plan. Change Package Access first.";
        }

        return null;
    }

    /**
     * What would be wrong with plan access where each app's lowest plan is
     * $lowest (app id => package rank; an app left out is in no plan).
     *
     * @param  array<int, int>  $lowest
     * @return list<string>
     */
    public function accessProblems(array $lowest): array
    {
        $apps     = $this->appRepository->allWithDependencies()->keyBy('id');
        $problems = [];

        foreach ($apps as $app) {
            $rank = $lowest[$app->id] ?? null;

            if ($rank === null) {
                continue;
            }

            /** @var AppDependency $need */
            foreach ($app->dependencies as $need) {
                $best = $apps
                    ->filter(fn (Application $b) => $b->family_key === $need->requires_family && $b->edition->covers($need->min_edition))
                    ->map(fn (Application $b) => $lowest[$b->id] ?? null)
                    ->filter(fn ($r) => $r !== null)
                    ->min();

                if ($best === null || $best > $rank) {
                    $needed     = $this->dependencies->familyName($need->requires_family, $need->min_edition);
                    $problems[] = $best === null
                        ? "{$app->name} is in {$this->planName($rank)}, but needs {$needed}, which is in no plan."
                        : "{$app->name} is in {$this->planName($rank)}, but needs {$needed}, which is only in {$this->planName($best)}.";
                }
            }
        }

        return $problems;
    }

    /** @return array<int, int> app id => rank of the lowest plan that includes it */
    public function lowestRanks(): array
    {
        return $this->packageRepository->lowestRanks();
    }

    private function planName(int $rank): string
    {
        return $this->packageRepository->nameOfRank($rank) ?? "plan {$rank}";
    }
}
