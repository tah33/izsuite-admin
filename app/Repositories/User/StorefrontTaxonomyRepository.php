<?php

namespace App\Repositories\User;

use App\Models\User\CatalogCategory;
use App\Models\User\CatalogCollection;
use App\Models\User\Storefront;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

/**
 * A storefront's categories and collections as its customers see them (Doc
 * 19 Phase C2): a category's online side by its link, the collections it
 * shows - active and within their days - and those whose names match what a
 * customer types.
 */
class StorefrontTaxonomyRepository
{
    /** The business's online category at $slug, with its name and picture. */
    public function category(Storefront $storefront, string $slug): ?CatalogCategory
    {
        return CatalogCategory::query()
            ->where('workspace_id', $storefront->workspace_id)
            ->where('slug', Str::lower($slug))
            ->with(['category:id,name', 'image'])
            ->first();
    }

    /** Phase C6a: the business's online categories' links, for a storefront's sitemap, with when each last changed. */
    public function sitemapCategories(Storefront $storefront): Collection
    {
        return CatalogCategory::query()->where('workspace_id', $storefront->workspace_id)->orderBy('position')->orderBy('id')->get(['slug', 'updated_at']);
    }

    /** Phase C4b: the link of the business's online category $id - for a banner's button - if it has one. */
    public function categorySlug(Storefront $storefront, int $id): ?string
    {
        return CatalogCategory::query()->where('workspace_id', $storefront->workspace_id)->whereKey($id)->value('slug');
    }

    /** The collections the storefront shows on $today, in their places, with their pictures. */
    public function collections(Storefront $storefront, string $today): Collection
    {
        return $this->shown($storefront, $today)->with('image')->orderBy('position')->orderBy('name')->get();
    }

    /** The collection the storefront shows at $slug on $today, with its picture. */
    public function collection(Storefront $storefront, string $slug, string $today): ?CatalogCollection
    {
        return $this->shown($storefront, $today)->where('slug', Str::lower($slug))->with('image')->first();
    }

    /** The online categories whose names hold $search, by name - the first $limit. */
    public function categoriesMatching(Storefront $storefront, string $search, int $limit): Collection
    {
        return CatalogCategory::query()
            ->where('workspace_id', $storefront->workspace_id)
            ->whereHas('category', fn (Builder $category) => $category->where('name', 'like', $this->like($search)))
            ->with('category:id,name')
            ->limit($limit)
            ->get(['id', 'item_category_id', 'slug']);
    }

    /** The collections the storefront shows on $today whose names hold $search - the first $limit. */
    public function collectionsMatching(Storefront $storefront, string $search, string $today, int $limit): Collection
    {
        return $this->shown($storefront, $today)->where('name', 'like', $this->like($search))->orderBy('name')->limit($limit)->get(['id', 'name', 'slug']);
    }

    /** The business's collections that are active and within their days on $today. */
    private function shown(Storefront $storefront, string $today): Builder
    {
        return CatalogCollection::query()
            ->where('workspace_id', $storefront->workspace_id)
            ->where('status', CatalogCollection::ACTIVE)
            ->where(fn (Builder $query) => $query->whereNull('available_from')->orWhereDate('available_from', '<=', $today))
            ->where(fn (Builder $query) => $query->whereNull('available_until')->orWhereDate('available_until', '>=', $today));
    }

    private function like(string $search): string
    {
        return '%'.addcslashes($search, '%_\\').'%';
    }
}
