<?php

namespace App\Repositories\Admin;

use App\Enums\Edition;
use App\Models\Frontend\AppDependency;
use App\Models\Frontend\Application;
use App\Models\User\WorkspaceApp;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;

class AppRepository
{
    /**
     * Get filtered, paginated apps list.
     */
    public function getPaginated(?string $search = null, int $perPage = 15): LengthAwarePaginator
    {
        $query = Application::query()->with(['category', 'packages:id,key,name,rank']);

        if (! empty($search)) {
            $query->where(fn ($q) => $q
                ->where('name', 'like', "%{$search}%")
                ->orWhere('family_key', 'like', "%{$search}%")
                ->orWhere('app_key', 'like', "%{$search}%")
                ->orWhereHas('category', fn ($c) => $c->where('name', 'like', "%{$search}%")));
        }

        // Families together, Lite before Full, in the Apps Center's order.
        return $query->orderBy('sort_order')->orderBy('family_key')
            ->orderByRaw("case when edition = 'lite' then 1 else 2 end")
            ->paginate($perPage)->withQueryString();
    }

    /**
     * Public listing: active apps only, optionally filtered.
     */
    public function getActivePaginated(
        ?string $search = null,
        ?string $category = null,
        ?string $status = null,
        int $perPage = 15,
    ): LengthAwarePaginator {
        $query = Application::query()->with('category')->where('is_active', true);

        if (! empty($search)) {
            $query->where(fn ($q) => $q
                ->where('name', 'like', "%{$search}%")
                ->orWhere('description', 'like', "%{$search}%")
                ->orWhereHas('category', fn ($c) => $c->where('name', 'like', "%{$search}%")));
        }

        // By the category's name, as the public list has always taken it.
        if (! empty($category)) {
            $query->whereHas('category', fn ($c) => $c->where('name', $category));
        }

        if (! empty($status)) {
            $query->where('status', $status);
        }

        return $query->orderBy('name')->orderBy('sort_order')->paginate($perPage)->withQueryString();
    }

    public function find(int $id): ?Application
    {
        return Application::find($id);
    }

    /** The app with key $appKey, listed or not. */
    public function findByKey(string $appKey): ?Application
    {
        return Application::where('app_key', $appKey)->first();
    }

    public function create(array $data): Application
    {
        return Application::create($data);
    }

    public function update(Application $app, array $data): Application
    {
        $app->update($data);

        return $app->fresh();
    }

    public function delete(Application $app): ?bool
    {
        return $app->delete();
    }

    /** Every app, in the catalog's order - families together. */
    public function allInCatalogOrder(): Collection
    {
        return Application::orderBy('sort_order')->orderBy('family_key')->get();
    }

    /** The apps with no category, in catalog order. */
    public function uncategorised(): Collection
    {
        return Application::whereNull('category_id')->orderBy('sort_order')->get();
    }

    /** Every app's id. */
    public function allIds(): SupportCollection
    {
        return Application::pluck('id');
    }

    /** Every app with what it needs turned on first. */
    public function allWithDependencies(): Collection
    {
        return Application::with('dependencies')->get();
    }

    /** The listed apps, with what each needs turned on first. */
    public function activeWithDependencies(): Collection
    {
        return Application::with('dependencies')->where('is_active', true)->get();
    }

    /**
     * Every requirement as family => required family, for following them
     * from one family to the next.
     */
    public function requirementPairs(): SupportCollection
    {
        return AppDependency::query()
            ->join('apps', 'apps.id', '=', 'app_dependencies.app_id')
            ->get(['apps.family_key as family', 'app_dependencies.requires_family as requires']);
    }

    /** The name of $family's $edition, if the family has one. */
    public function editionName(string $family, Edition $edition): ?string
    {
        return Application::where('family_key', $family)->where('edition', $edition)->value('name');
    }

    /** The name of one of $family's editions, if it has any. */
    public function anyEditionName(string $family): ?string
    {
        return Application::where('family_key', $family)->value('name');
    }

    /** Whether $family has an $edition edition. */
    public function hasEdition(string $family, Edition $edition): bool
    {
        return Application::where('family_key', $family)->where('edition', $edition)->exists();
    }

    /** The editions of $family. */
    public function editionsOf(string $family): Collection
    {
        return Application::where('family_key', $family)->get();
    }

    /** The apps of every family but $app's, by name. */
    public function otherFamilies(Application $app): Collection
    {
        return Application::where('family_key', '!=', $app->family_key)->orderBy('name')->get();
    }

    /** The families that have a Full edition. */
    public function familiesWithFull(): SupportCollection
    {
        return Application::where('edition', Edition::Full)->pluck('family_key');
    }

    /** Whether any business has ever turned $app on. */
    public function hasBeenUsed(Application $app): bool
    {
        return WorkspaceApp::where('app_id', $app->id)->exists();
    }

    /** $app with what its edit form shows: its requirements and its plans. */
    public function withRequirements(Application $app): Application
    {
        return $app->load(['dependencies', 'packages']);
    }

    /**
     * Gives the other edition of $app's family $app's sort order, so Lite and
     * Full take the same place in the sidebar and the Apps Center.
     */
    public function shareFamilyOrder(Application $app): void
    {
        Application::where('family_key', $app->family_key)
            ->whereKeyNot($app->getKey())
            ->update(['sort_order' => $app->sort_order]);
    }

    /** Sets what $app needs turned on first - $family, at $min at least. */
    public function saveRequirement(Application $app, string $family, Edition $min): AppDependency
    {
        return $app->dependencies()->updateOrCreate(['requires_family' => $family], ['min_edition' => $min]);
    }

    /** One of app $appId's requirements. */
    public function findRequirement(int $appId, int $dependencyId): ?AppDependency
    {
        return AppDependency::where('app_id', $appId)->find($dependencyId);
    }

    public function deleteRequirement(AppDependency $dependency): ?bool
    {
        return $dependency->delete();
    }
}
