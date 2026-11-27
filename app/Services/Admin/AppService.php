<?php

namespace App\Services\Admin;

use App\Enums\Edition;
use App\Models\Frontend\AppDependency;
use App\Models\Frontend\Application;
use App\Models\User\User;
use App\Repositories\Admin\AppCategoryRepository;
use App\Repositories\Admin\AppRepository;
use App\Repositories\Billing\PackageRepository;
use App\Repositories\User\WorkspaceAppRepository;
use App\Services\Shared\ActivityLogService;
use App\Services\Support\ImageService;
use App\Services\User\Apps\CatalogVersion;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AppService
{
    public function __construct(
        protected AppRepository $appRepository,
        protected AppCategoryRepository $appCategoryRepository,
        protected ImageService $imageService,
        protected AppCatalogRules $rules,
        protected WorkspaceAppRepository $workspaceApps,
        protected CriticalActionService $critical,
        protected PackageRepository $packageRepository,
    ) {}

    /**
     * Retires $app (Doc 15 section 15.10 - Phase E2), a critical action: it
     * leaves the Apps Center and the website, and goes off for every
     * business that has it on - their records kept, as turning it off keeps
     * them. How many businesses had it on.
     */
    public function retire(Application $app, User $admin, ?string $password, ?string $reason): int
    {
        $this->critical->confirm($admin, $password, $reason, "Retire {$app->name}");

        $affected = DB::transaction(function () use ($app) {
            $affected = $this->workspaceApps->deactivateEverywhere($app);
            $this->appRepository->update($app, ['status' => Application::RETIRED, 'is_active' => false]);

            return $affected;
        });

        ActivityLogService::record('updated', "Retired app \"{$app->name}\": off for {$affected} ".Str::plural('business', $affected), $app, ['reason' => $reason], $admin);

        return $affected;
    }

    /** Lists left empty mean all; an app does not conflict with its own family. */
    private function catalogueFields(array $data): array
    {
        if (array_key_exists('countries', $data)) {
            $data['countries'] = ! empty($data['countries']) ? array_values($data['countries']) : null;
        }

        if (array_key_exists('conflicts', $data)) {
            $conflicts         = array_values(array_diff($data['conflicts'] ?? [], [$data['family_key'] ?? null]));
            $data['conflicts'] = $conflicts ?: null;
        }

        return $data;
    }

    /**
     * Get filtered, paginated apps list.
     */
    public function getFilteredApps(?string $search = null, int $perPage = 15): LengthAwarePaginator
    {
        return $this->appRepository->getPaginated($search, $perPage);
    }

    /**
     * Find app by ID.
     */
    public function find(int $id): ?Application
    {
        return $this->appRepository->find($id);
    }

    /**
     * Create a new app. An uploaded $data['image'] becomes its logo.
     */
    public function create(array $data): Application
    {
        $data    = $this->catalogueFields([...$data, 'countries' => $data['countries'] ?? null, 'conflicts' => $data['conflicts'] ?? null]);
        $image   = $data['image'] ?? null;
        $plans   = $this->planIds($data['package_ids'] ?? []);
        unset($data['image'], $data['package_ids']);

        if ($image instanceof UploadedFile && $image->isValid()) {
            $data['logo_url'] = $this->imageService->storePublic($image, 'apps');
        }

        // A new app needs nothing and nothing needs it yet, so any plans are allowed.
        $app = DB::transaction(function () use ($data, $plans) {
            $app = $this->shareFamilyOrder($this->appRepository->create($data));
            $this->setPlans($app, $plans);

            return $app;
        });

        ActivityLogService::record('created', "Created app \"{$app->name}\"", $app);

        return $app;
    }

    /**
     * Update an app. An uploaded $data['image'] replaces its logo.
     */
    public function update(Application $app, array $data): Application
    {
        $data    = $this->catalogueFields([...$data, 'countries' => $data['countries'] ?? null, 'conflicts' => $data['conflicts'] ?? null]);
        $image   = $data['image'] ?? null;
        // Plans change only when the form sends the field: none chosen means no plan.
        $setPlans = array_key_exists('package_ids', $data);
        $plans    = $this->planIds($data['package_ids'] ?? []);
        unset($data['image'], $data['package_ids']);

        if ($setPlans) {
            $this->checkPlans($app, $plans);
        }

        if ($image instanceof UploadedFile && $image->isValid()) {
            $data['logo_url'] = $this->imageService->storePublic($image, 'apps', $app->logo_url);
        }

        $updated = DB::transaction(function () use ($app, $data, $plans, $setPlans) {
            $updated = $this->shareFamilyOrder($this->appRepository->update($app, $data));

            if ($setPlans) {
                $this->setPlans($updated, $plans);
            }

            return $updated;
        });

        ActivityLogService::record('updated', "Updated app \"{$app->name}\"", $app);

        return $updated;
    }

    /** Every plan, lowest first, for the form's Plan list: id => name. */
    public function planNames(): Collection
    {
        return $this->packageRepository->namesByRank();
    }

    /** @return list<int> the chosen package ids, each once, in order */
    private function planIds(?array $ids): array
    {
        return collect($ids ?? [])->map(fn ($id) => (int) $id)->unique()->sort()->values()->all();
    }

    /**
     * Refuses putting $app in exactly the plans $packageIds when a plan would
     * then hold $app, or an app that needs it, without what it needs - the
     * same rule as Package Access.
     *
     * @param  list<int>  $packageIds
     */
    private function checkPlans(Application $app, array $packageIds): void
    {
        $access = $this->rules->access();
        unset($access[$app->id]);

        if ($packageIds) {
            $access[$app->id] = $packageIds;
        }

        if ($problems = $this->rules->accessProblems($access)) {
            throw ValidationException::withMessages(['package_ids' => $problems]);
        }
    }

    /**
     * Puts $app in exactly the plans $packageIds - none for an empty list.
     * Does nothing when it is there already.
     *
     * @param  list<int>  $packageIds
     */
    private function setPlans(Application $app, array $packageIds): void
    {
        $current = $this->rules->access()[$app->id] ?? [];
        sort($current);

        if ($packageIds === $current) {
            return;
        }

        $this->packageRepository->setAppAccess($app->id, $packageIds);
        CatalogVersion::bump();

        $names = $this->packageRepository->namesByRank()->only($packageIds)->implode(', ');
        ActivityLogService::record('updated', "Put app \"{$app->name}\" in ".($names ?: 'no plan'), $app);
    }

    /**
     * Both editions of a family share one sort order, so Lite and Full take
     * the same place in the sidebar and the Apps Center.
     */
    private function shareFamilyOrder(Application $app): Application
    {
        $this->appRepository->shareFamilyOrder($app);

        return $app;
    }

    /**
     * Delete an app, and its logo.
     */
    public function delete(Application $app): ?bool
    {
        $name = $app->name;
        $this->imageService->deletePublic($app->logo_url);
        $deleted = $this->appRepository->delete($app);

        ActivityLogService::record('deleted', "Deleted app \"{$name}\"");

        return $deleted;
    }

    /**
     * Why $app cannot be deleted, if it cannot: once a business has used it,
     * its records point at it.
     */
    public function deletionProblem(Application $app): ?string
    {
        return $this->hasBeenUsed($app)
            ? 'Businesses have used this app, and its records point at it. Set it inactive instead.'
            : null;
    }

    /** Families that already have a Full edition, for "Add Full edition": family => index. */
    public function familiesWithFull(): Collection
    {
        return $this->appRepository->familiesWithFull()->flip();
    }

    /**
     * A new app for the create form - blank, or the Full edition of Lite app
     * $fullOf, which starts from the Lite app's family, category, icon and order.
     */
    public function newApp(?int $fullOf): Application
    {
        $app  = new Application(['status' => 'available', 'is_active' => true, 'edition' => Edition::Full]);
        $lite = $fullOf ? $this->appRepository->find($fullOf) : null;

        if ($lite && $lite->edition === Edition::Lite) {
            $app->fill([
                'name'        => trim(Str::replaceLast(' Lite', '', $lite->name)),
                'family_key'  => $lite->family_key,
                'app_key'     => $lite->family_key,
                'category_id' => $lite->category_id,
                'icon'        => $lite->icon,
                'sort_order'  => $lite->sort_order,
                'description' => $lite->description,
            ]);
        }

        return $app;
    }

    /** $app with its requirements and plans, for the edit form. */
    public function withRequirements(Application $app): Application
    {
        return $this->appRepository->withRequirements($app);
    }

    /** Every other family, by its Full name (else Lite), for "Requires". */
    public function otherFamilyNames(Application $app): Collection
    {
        return $this->familyNames($this->appRepository->otherFamilies($app))->sort();
    }

    /** Every app family, by its Full edition's name (else Lite's), in catalog order. */
    public function catalogFamilyNames(): Collection
    {
        return $this->familyNames($this->appRepository->allInCatalogOrder());
    }

    /** Active categories, in the Apps Center's order: id => name. */
    public function categoryNames(): Collection
    {
        return $this->appCategoryRepository->activeNames();
    }

    /** Whether a business has used $app, so its records point at it. */
    public function hasBeenUsed(Application $app): bool
    {
        return $this->appRepository->hasBeenUsed($app);
    }

    /** Why $app cannot need $family at $min - or null when it can. */
    public function requirementProblem(Application $app, string $family, Edition $min): ?string
    {
        return $this->rules->dependencyProblem($app, $family, $min);
    }

    /** Sets what $app needs turned on first. */
    public function saveRequirement(Application $app, string $family, Edition $min): AppDependency
    {
        $dependency = $this->appRepository->saveRequirement($app, $family, $min);

        ActivityLogService::record('updated', "Set \"{$app->name}\" to require {$family}", $app);

        return $dependency;
    }

    /**
     * Takes one of app $appId's requirements away; false when it has no such
     * requirement.
     */
    public function deleteRequirement(int $appId, int $dependencyId): bool
    {
        $dependency = $this->appRepository->findRequirement($appId, $dependencyId);

        if (! $dependency) {
            return false;
        }

        $this->appRepository->deleteRequirement($dependency);

        return true;
    }

    /** $apps' families, each by its Full edition's name (else Lite's): family => name. */
    private function familyNames(Collection $apps): Collection
    {
        return $apps->groupBy('family_key')
            ->map(fn ($editions) => $editions->sortByDesc(fn (Application $e) => $e->edition->rank())->first()->name);
    }
}
