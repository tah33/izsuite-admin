<?php

namespace App\Repositories\User;

use App\Models\User\CatalogCategory;
use App\Models\User\ItemCategory;
use App\Models\User\Workspace;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/** The business's item categories as its storefronts show them (Doc 19 Phase B2). */
class CatalogCategoryRepository
{
    /** Its item categories by name, each with its online side and how many of its items are in the catalog. */
    public function categoriesFor(Workspace $workspace): Collection
    {
        return ItemCategory::query()
            ->where('workspace_id', $workspace->id)
            ->with('catalogCategory.image')
            ->withCount(['items as listed_count' => fn (Builder $query) => $query->has('catalogProduct')])
            ->orderBy('name')
            ->orderBy('id')
            ->get();
    }

    public function findCategory(Workspace $workspace, int $id): ?ItemCategory
    {
        return ItemCategory::query()->where('workspace_id', $workspace->id)->with('catalogCategory.image')->withCount(['items as listed_count' => fn (Builder $query) => $query->has('catalogProduct')])->find($id);
    }

    /** Whether another category's online side - $exceptId aside - has $slug. */
    public function slugTaken(Workspace $workspace, string $slug, ?int $exceptId = null): bool
    {
        return CatalogCategory::query()->where('workspace_id', $workspace->id)->where('slug', $slug)->when($exceptId, fn (Builder $query, int $id) => $query->whereKeyNot($id))->exists();
    }

    /** Its online side made, or changed; whether anything changed. */
    public function save(Workspace $workspace, ItemCategory $category, array $data): bool
    {
        $online = $category->catalogCategory ?? new CatalogCategory;
        $online->fill($data);
        $online->workspace_id     = $workspace->id;
        $online->item_category_id = $category->id;
        $changed = ! $online->exists || $online->isDirty();
        $online->save();

        $category->setRelation('catalogCategory', $online->load('image'));

        return $changed;
    }
}
