<?php

namespace App\Services\Admin;

use App\Enums\Edition;
use App\Models\Frontend\AppDependency;
use App\Models\Frontend\Application;
use App\Repositories\Admin\AppCategoryRepository;
use App\Repositories\Admin\AppRepository;
use App\Services\Shared\ActivityLogService;
use App\Services\Support\ImageService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class AppService
{
    public function __construct(
        protected AppRepository $appRepository,
        protected AppCategoryRepository $appCategoryRepository,
        protected ImageService $imageService,
        protected AppCatalogRules $rules,
    ) {}

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
        $image = $data['image'] ?? null;
        unset($data['image']);

        if ($image instanceof UploadedFile && $image->isValid()) {
            $data['logo_url'] = $this->imageService->storePublic($image, 'apps');
        }

        $app = $this->shareFamilyOrder($this->appRepository->create($data));

        ActivityLogService::record('created', "Created app \"{$app->name}\"", $app);

        return $app;
    }

    /**
     * Update an app. An uploaded $data['image'] replaces its logo.
     */
    public function update(Application $app, array $data): Application
    {
        $image = $data['image'] ?? null;
        unset($data['image']);

        if ($image instanceof UploadedFile && $image->isValid()) {
            $data['logo_url'] = $this->imageService->storePublic($image, 'apps', $app->logo_url);
        }

        $updated = $this->shareFamilyOrder($this->appRepository->update($app, $data));

        ActivityLogService::record('updated', "Updated app \"{$app->name}\"", $app);

        return $updated;
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
