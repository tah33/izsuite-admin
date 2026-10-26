<?php

namespace App\Services\Admin;

use App\Models\Billing\Package;
use App\Repositories\Billing\PackageRepository;
use App\Services\Shared\ActivityLogService;
use Illuminate\Database\Eloquent\Collection;

/**
 * Billing Starter, Standard and Premium. Their keys and ranks are fixed (the
 * code relies on them); names, descriptions, storage limits and whether they
 * are offered can change.
 */
class PackageService
{
    public function __construct(
        protected PackageRepository $packageRepository,
    ) {}

    /** Every plan, lowest first, with how many apps and businesses it has. */
    public function listWithCounts(): Collection
    {
        return $this->packageRepository->allByRankWithCounts();
    }

    /** Every plan, lowest first. */
    public function all(): Collection
    {
        return $this->packageRepository->allByRank();
    }

    public function findOrFail(int $id): Package
    {
        return $this->packageRepository->findOrFail($id);
    }

    public function update(Package $package, array $data): Package
    {
        $this->packageRepository->update($package, $data);

        ActivityLogService::record('updated', "Updated plan \"{$package->name}\"", $package);

        return $package;
    }
}
