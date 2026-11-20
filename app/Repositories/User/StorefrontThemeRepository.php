<?php

namespace App\Repositories\User;

use App\Models\User\CatalogCategory;
use App\Models\User\CatalogCollection;
use App\Models\User\Storefront;
use App\Models\User\Workspace;
use App\Models\User\WorkspaceFile;
use Illuminate\Database\Eloquent\Collection;

/**
 * A storefront's theme (Doc 19 Phase C4a): its draft and the one published,
 * and its own pictures the theme chooses among - and, from Phase C4b, the
 * business's categories and collections its home's sections may name.
 */
class StorefrontThemeRepository
{
    /** Its pictures, oldest first. */
    public function pictures(Storefront $storefront): Collection
    {
        return $storefront->brandingFiles()->get();
    }

    /** Its picture $id, if it is one of its own. */
    public function picture(Storefront $storefront, int $id): ?WorkspaceFile
    {
        return $storefront->brandingFiles()->whereKey($id)->first();
    }

    /** Phase C4b: the business's online categories, by name. */
    public function categories(Workspace $workspace): Collection
    {
        return CatalogCategory::query()
            ->join('item_categories', 'item_categories.id', '=', 'catalog_categories.item_category_id')
            ->where('catalog_categories.workspace_id', $workspace->id)
            ->orderBy('item_categories.name')
            ->orderBy('catalog_categories.id')
            ->get(['catalog_categories.id', 'item_categories.name']);
    }

    /** Phase C4b: the business's collections, by name. */
    public function collections(Workspace $workspace): Collection
    {
        return CatalogCollection::query()->where('workspace_id', $workspace->id)->orderBy('name')->orderBy('id')->get(['id', 'name']);
    }

    public function pictureCount(Storefront $storefront): int
    {
        return $storefront->brandingFiles()->count();
    }

    /** The draft kept; whether it changed. */
    public function saveDraft(Storefront $storefront, array $draft): bool
    {
        $storefront->theme_draft = $draft;

        return $this->saved($storefront);
    }

    /** $theme published - the draft too - from now on. */
    public function publish(Storefront $storefront, array $theme): void
    {
        $storefront->forceFill(['theme' => $theme, 'theme_draft' => $theme, 'theme_published_at' => now()])->save();
    }

    /** The draft put back to $published; whether it changed. */
    public function discard(Storefront $storefront, array $published): bool
    {
        $storefront->theme_draft = $published;

        return $this->saved($storefront);
    }

    private function saved(Storefront $storefront): bool
    {
        $changed = $storefront->isDirty();
        $storefront->save();

        return $changed;
    }
}
