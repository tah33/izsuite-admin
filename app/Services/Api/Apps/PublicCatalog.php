<?php

namespace App\Services\Api\Apps;

use App\Models\Billing\Package;
use App\Models\Billing\WorkspaceSubscription;
use App\Models\Frontend\AppCategory;
use App\Models\Frontend\Application;
use App\Models\User\Workspace;
use App\Repositories\Admin\AppCategoryRepository;
use App\Repositories\Admin\AppRepository;
use App\Repositories\Billing\PackageRepository;
use App\Services\Billing\PackagePricing;
use App\Services\Billing\PlanAvailability;
use App\Support\Countries;
use Illuminate\Support\Collection;

/**
 * izSuite's apps and plans as the public website shows them (Doc 13 sections
 * 2 and 4): every listed app family by category, with the plan each edition
 * needs, and the active plans with their limits and the apps they include -
 * the admin's catalog, never a list kept in the website.
 */
class PublicCatalog
{
    public function __construct(
        protected AppRepository $apps,
        protected AppCategoryRepository $categories,
        protected PackageRepository $packages,
        protected PackagePricing $pricing,
        protected PlanAvailability $availability,
    ) {}

    /** @return array{categories: list<array>, packages: list<array>} */
    public function get(): array
    {
        $lowest   = $this->packages->lowestNames();
        $families = $this->apps->activeWithDependencies()->groupBy('family_key');

        return [
            'categories' => $this->categories->activeInOrder()
                ->map(fn (AppCategory $category) => [
                    'id'   => $category->id,
                    'name' => $category->name,
                    'icon' => $category->icon,
                    'apps' => $families
                        ->filter(fn (Collection $editions) => Application::primaryOf($editions)->category_id === $category->id)
                        ->sortBy(fn (Collection $editions) => [Application::primaryOf($editions)->sort_order, Application::primaryOf($editions)->name])
                        ->map(fn (Collection $editions, string $family) => $this->family($family, $editions, $lowest))
                        ->values()
                        ->all(),
                ])
                ->filter(fn (array $category) => $category['apps'] !== [])
                ->values()
                ->all(),
            'packages'   => $this->packages(),
        ];
    }

    /**
     * The plans on offer, lowest first: what each costs - prices not yet
     * announced, and those on a cycle it is not offered on, are null - its
     * trial, limits, levels and the apps it includes. The Pricing page and a
     * business's Subscription compare them alike: the page in each plan's
     * main currency, a business in the one it pays in (Doc 15 Phase C3a).
     *
     * Where a plan is offered (Phase E3a): the page names the countries of a
     * plan offered in some only; a business sees those offered where it is
     * billed - every plan before it gives its billing details - and its own,
     * with the terms of the plan's version it is held to (Phase E3b).
     *
     * @return list<array>
     */
    public function packages(?Workspace $workspace = null): array
    {
        $country = $workspace ? $this->availability->countryOf($workspace) : null;
        $held    = $workspace?->terms();

        return $this->packages->activeWithApps()
            ->filter(fn (Package $package) => $country === null || $package->offeredIn($country) || $package->id === $workspace->package_id)
            ->map(function (Package $package) use ($workspace, $held) {
                // The business's own plan with the terms it is held to (Doc 15 Phase E3b).
                $terms = $held && $package->id === $workspace->package_id ? $held : $package;

                return [
                    'key'              => $package->key,
                    'name'             => $package->name,
                    'description'      => $package->description,
                    'free'             => $package->isFree(),
                    'price_currency'   => $this->pricing->currencyFor($package, $workspace),
                    'price_monthly'    => $this->price($package, WorkspaceSubscription::MONTHLY, $workspace),
                    'price_yearly'     => $this->price($package, WorkspaceSubscription::YEARLY, $workspace),
                    'cycles'           => $package->cycles(),
                    'trial_days'       => $package->trial_days,
                    'removes_branding' => $terms->removes_branding,
                    'reporting_level'  => Package::REPORTING_LEVELS[$terms->reporting_level] ?? null,
                    'support_level'    => Package::SUPPORT_LEVELS[$terms->support_level] ?? null,
                    'storage_limit_mb' => $terms->storage_limit_mb,
                    'business_limit'   => $terms->business_limit,
                    'user_limit'       => $terms->user_limit,
                    'location_limit'   => $terms->location_limit,
                    // Each app once, by the highest edition the plan has: Sales, not Sales and Sales Lite.
                    'apps'             => $package->apps->where('status', 'available')->groupBy('family_key')->map(fn (Collection $editions) => Application::primaryOf($editions)->name)->values()->all(),
                    'coming_soon'      => $package->apps->where('status', 'coming_soon')->count(),
                    ...($workspace ? [] : ['offered_in' => array_map(fn (string $code) => Countries::name($code) ?? $code, $package->countries ?? [])]),
                ];
            })
            ->values()
            ->all();
    }

    /** $package's $cycle price for $workspace - null while not announced, or when it is not offered on $cycle. */
    private function price(Package $package, string $cycle, ?Workspace $workspace): ?string
    {
        return $package->offersCycle($cycle) ? ($this->pricing->price($package, $cycle, $workspace)['amount'] ?? null) : null;
    }

    /** @param array<int, string> $lowest app id => the lowest plan that includes it */
    private function family(string $family, Collection $editions, array $lowest): array
    {
        $primary = Application::primaryOf($editions);

        return [
            'family_key'  => $family,
            'name'        => $primary->name,
            'icon'        => $primary->icon,
            'description' => $primary->description,
            'coming_soon' => $editions->every(fn (Application $app) => $app->status === 'coming_soon'),
            'editions'    => $editions
                ->sortBy(fn (Application $app) => $app->edition->rank())
                ->map(fn (Application $app) => ['name' => $app->name, 'package' => $lowest[$app->id] ?? null])
                ->values()
                ->all(),
        ];
    }
}
