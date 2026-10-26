<?php

namespace App\Services\Admin;

use App\Repositories\Admin\AppCategoryRepository;
use App\Repositories\Admin\AppRepository;
use App\Repositories\Billing\PackageRepository;
use App\Services\Shared\ActivityLogService;
use App\Services\User\Apps\CatalogVersion;
use Illuminate\Support\Facades\DB;

/**
 * Which plan includes which app, as one matrix (Doc 01 section 3).
 *
 * A higher plan includes everything a lower one does, so only an app's lowest
 * ticked plan counts: saving ticks every plan above it as well. Saving is
 * refused while a plan would hold an app without what that app needs.
 */
class PackageAccessService
{
    public function __construct(
        protected PackageRepository $packageRepository,
        protected AppCategoryRepository $appCategoryRepository,
        protected AppRepository $appRepository,
        protected AppCatalogRules $rules,
    ) {}

    /** The matrix: the plans, the apps by category, and which plans include each app. */
    public function matrix(): array
    {
        return [
            'packages'      => $this->packageRepository->allByRank(),
            'categories'    => $this->appCategoryRepository->allWithApps(),
            'uncategorised' => $this->appRepository->uncategorised(),
            'access'        => $this->packageRepository->accessByApp(),
        ];
    }

    /**
     * Saves the ticked boxes - app id => its ticked package ids - as each
     * app's lowest plan and every plan above it. Returns what is wrong with
     * them instead, when something is: nothing is saved then.
     *
     * @return list<string>
     */
    public function save(array $access): array
    {
        $packages = $this->packageRepository->allByRank();
        $ranks    = $packages->pluck('rank', 'id');
        $appIds   = $this->appRepository->allIds()->flip();

        // app id => rank of the lowest plan ticked for it.
        $lowest = [];

        foreach ($access as $appId => $packageIds) {
            $ticked = collect((array) $packageIds)->map(fn ($id) => $ranks[(int) $id] ?? null)->filter(fn ($r) => $r !== null);

            if ($appIds->has((int) $appId) && $ticked->isNotEmpty()) {
                $lowest[(int) $appId] = $ticked->min();
            }
        }

        if ($problems = $this->rules->accessProblems($lowest)) {
            return $problems;
        }

        DB::transaction(function () use ($lowest, $packages) {
            $rows = [];

            foreach ($lowest as $appId => $rank) {
                foreach ($packages->where('rank', '>=', $rank) as $package) {
                    $rows[] = ['package_id' => $package->id, 'app_id' => $appId];
                }
            }

            $this->packageRepository->replaceAccess($rows);
        });

        CatalogVersion::bump();

        ActivityLogService::record('updated', 'Updated package access');

        return [];
    }
}
