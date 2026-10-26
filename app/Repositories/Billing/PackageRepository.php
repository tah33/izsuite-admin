<?php

namespace App\Repositories\Billing;

use App\Enums\Edition;
use App\Models\Billing\Package;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\DB;

/**
 * The plans - Billing Starter, Standard and Premium - and which apps each one
 * includes (the package_apps table).
 */
class PackageRepository
{
    /** Every plan, lowest first. */
    public function allByRank(): Collection
    {
        return Package::orderBy('rank')->get();
    }

    /** Every plan, lowest first, with how many apps and businesses it has. */
    public function allByRankWithCounts(): Collection
    {
        return Package::withCount(['apps', 'workspaces'])->orderBy('rank')->get();
    }

    public function findOrFail(int $id): Package
    {
        return Package::findOrFail($id);
    }

    public function update(Package $package, array $data): Package
    {
        $package->update($data);

        return $package;
    }

    /** The name of the plan at $rank. */
    public function nameOfRank(int $rank): ?string
    {
        return Package::where('rank', $rank)->value('name');
    }

    /** Which plans include each app: app id => its package ids. */
    public function accessByApp(): SupportCollection
    {
        return DB::table('package_apps')->get()
            ->groupBy('app_id')
            ->map(fn ($rows) => $rows->pluck('package_id')->all());
    }

    /** @return array<int, int> app id => rank of the lowest plan that includes it */
    public function lowestRanks(): array
    {
        return DB::table('package_apps')
            ->join('packages', 'packages.id', '=', 'package_apps.package_id')
            ->groupBy('package_apps.app_id')
            ->selectRaw('package_apps.app_id as app_id, min(packages.`rank`) as lowest')
            ->pluck('lowest', 'app_id')
            ->map(fn ($rank) => (int) $rank)
            ->all();
    }

    /** The plan's Lite apps that are listed and available. */
    public function availableLiteApps(Package $package): Collection
    {
        return $package->apps()
            ->where('edition', Edition::Lite)
            ->where('is_active', true)
            ->where('status', 'available')
            ->get();
    }

    /**
     * Replaces all of plan access with $rows - package_id and app_id each.
     *
     * @param  list<array{package_id: int, app_id: int}>  $rows
     */
    public function replaceAccess(array $rows): void
    {
        DB::table('package_apps')->delete();

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('package_apps')->insert($chunk);
        }
    }
}
