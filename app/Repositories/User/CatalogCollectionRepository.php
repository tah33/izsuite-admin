<?php

namespace App\Repositories\User;

use App\Models\User\CatalogCollection;
use App\Models\User\CatalogProduct;
use App\Models\User\ItemBrand;
use App\Models\User\Workspace;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/** The business's collections of products (Doc 19 Phase B2), in their order, then by name. */
class CatalogCollectionRepository
{
    private const WITH = ['brand:id,name', 'image', 'products:catalog_products.id,catalog_products.title'];

    public function listFor(Workspace $workspace): Collection
    {
        return CatalogCollection::query()->where('workspace_id', $workspace->id)->with(self::WITH)->orderBy('position')->orderBy('name')->get();
    }

    public function find(Workspace $workspace, int $id): ?CatalogCollection
    {
        return CatalogCollection::query()->where('workspace_id', $workspace->id)->with(self::WITH)->find($id);
    }

    /** Whether another collection of the business - $exceptId aside - has $value as its $column (name or slug). */
    public function taken(Workspace $workspace, string $column, string $value, ?int $exceptId = null): bool
    {
        return CatalogCollection::query()->where('workspace_id', $workspace->id)->where($column, $value)->when($exceptId, fn (Builder $query, int $id) => $query->whereKeyNot($id))->exists();
    }

    /** Made for the business, so its workspace is never read from $data; holding $productIds in their order. */
    public function create(Workspace $workspace, array $data, array $productIds): CatalogCollection
    {
        $collection = new CatalogCollection($data);
        $collection->workspace_id = $workspace->id;
        $collection->save();

        $collection->products()->sync($this->ordered($productIds));

        return $collection->load(self::WITH);
    }

    /** Whether anything changed. */
    public function update(CatalogCollection $collection, array $data, array $productIds): bool
    {
        $collection->update($data);
        $synced = $collection->products()->sync($this->ordered($productIds));
        $collection->load(self::WITH);

        return $collection->wasChanged() || array_filter($synced) !== [];
    }

    public function delete(CatalogCollection $collection): void
    {
        $collection->delete();
    }

    /** The business's listings by title - what a collection is chosen from. */
    public function listingsOf(Workspace $workspace): Collection
    {
        return CatalogProduct::query()->where('workspace_id', $workspace->id)->orderBy('title')->orderBy('id')->get(['id', 'title', 'status']);
    }

    /** The business's brands by name. */
    public function brandsOf(Workspace $workspace): Collection
    {
        return ItemBrand::query()->where('workspace_id', $workspace->id)->orderBy('name')->get(['id', 'name']);
    }

    /** How many listings have an item of each of $brandIds: brand id => count. */
    public function listingsByBrand(Workspace $workspace, array $brandIds): array
    {
        if ($brandIds === []) {
            return [];
        }

        return CatalogProduct::query()
            ->join('items', 'items.id', '=', 'catalog_products.item_id')
            ->where('catalog_products.workspace_id', $workspace->id)
            ->whereIn('items.brand_id', $brandIds)
            ->groupBy('items.brand_id')
            ->selectRaw('items.brand_id as brand_id, count(*) as listings')
            ->pluck('listings', 'brand_id')
            ->map(fn ($count) => (int) $count)
            ->all();
    }

    /** product id => its place, from 1. */
    private function ordered(array $productIds): array
    {
        $places = [];

        foreach (array_values($productIds) as $index => $id) {
            $places[$id] = ['position' => $index + 1];
        }

        return $places;
    }
}
