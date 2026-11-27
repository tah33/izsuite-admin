<?php

namespace App\Services\Admin;

use App\Models\User\User;
use App\Repositories\Admin\AppCategoryRepository;
use App\Repositories\Admin\AppRepository;
use App\Repositories\Billing\PackageRepository;
use App\Services\Shared\ActivityLogService;
use App\Services\User\Apps\CatalogVersion;
use Illuminate\Support\Facades\DB;

/**
 * Which plan includes which app, as one matrix (Doc 01 section 3).
 *
 * Each plan has its own apps: an app can be in one plan, several or all,
 * exactly as ticked. Saving is refused while a plan would hold an app
 * without what that app needs.
 */
class PackageAccessService
{
    public function __construct(
        protected PackageRepository $packageRepository,
        protected AppCategoryRepository $appCategoryRepository,
        protected AppRepository $appRepository,
        protected AppCatalogRules $rules,
        protected CriticalActionService $criticalActions,
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
     * Saves the ticked boxes - app id => its ticked package ids - exactly as
     * ticked: each plan holds only the apps ticked for it. Returns what is
     * wrong with them instead, when something is: nothing is saved then.
     *
     * It changes every plan's apps at once, so it is a critical action (Doc
     * 15 section 15.25): $admin's password again and a reason.
     *
     * @return list<string>
     */
    public function save(array $access, User $admin, ?string $password, ?string $reason): array
    {
        $packageIds = $this->packageRepository->allByRank()->pluck('id')->flip();
        $appIds     = $this->appRepository->allIds()->flip();

        // app id => the package ids ticked for it, unknown ids dropped.
        $ticked = [];

        foreach ($access as $appId => $ids) {
            $ids = collect((array) $ids)->map(fn ($id) => (int) $id)->filter(fn ($id) => $packageIds->has($id))->unique()->sort()->values()->all();

            if ($appIds->has((int) $appId) && $ids) {
                $ticked[(int) $appId] = $ids;
            }
        }

        if ($problems = $this->rules->accessProblems($ticked)) {
            return $problems;
        }

        $this->criticalActions->confirm($admin, $password, $reason, 'change which apps every plan includes');

        $before = $this->packageRepository->accessByApp();

        DB::transaction(function () use ($ticked) {
            $rows = [];

            foreach ($ticked as $appId => $ids) {
                foreach ($ids as $packageId) {
                    $rows[] = ['package_id' => $packageId, 'app_id' => $appId];
                }
            }

            $this->packageRepository->replaceAccess($rows);
        });

        CatalogVersion::bump();

        ActivityLogService::record('updated', 'Updated package access', null, ['changes' => $this->accessChanges($before, $this->packageRepository->accessByApp())]);

        return [];
    }

    /**
     * Each app whose plans changed, as the audit log shows it: "app 12",
     * from its plan ids before to after.
     *
     * @return list<array{setting: string, from: string|null, to: string|null}>
     */
    private function accessChanges(array|\Illuminate\Support\Collection $before, array|\Illuminate\Support\Collection $after): array
    {
        $before = collect($before)->map(fn ($ids) => collect($ids)->sort()->values()->implode(', '));
        $after  = collect($after)->map(fn ($ids) => collect($ids)->sort()->values()->implode(', '));

        return $before->keys()->merge($after->keys())->unique()->sort()
            ->filter(fn ($appId) => ($before[$appId] ?? '') !== ($after[$appId] ?? ''))
            ->map(fn ($appId) => ['setting' => "app {$appId} (plan ids)", 'from' => ($before[$appId] ?? '') ?: null, 'to' => ($after[$appId] ?? '') ?: null])
            ->values()->all();
    }
}
