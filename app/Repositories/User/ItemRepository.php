<?php

namespace App\Repositories\User;

use App\Models\User\Item;
use App\Models\User\ItemComponent;
use App\Models\User\Workspace;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\LazyCollection;

/**
 * Items belong to one business (workspace). Every lookup is scoped to it, so
 * another business's item - the same owner's or anyone else's - is simply not
 * found.
 */
class ItemRepository
{
    /** What ItemResource shows of an item besides its own columns. */
    private const SHOWN_WITH = [
        'category:id,name',
        'brand:id,name',
        'salesTax:id,name,code,rate,treatment,is_active',
        'purchaseTax:id,name,code,rate,treatment,is_active',
        'components.component',
        'image',
    ];

    /**
     * A page of the business's items, alphabetical with the id as
     * tie-breaker. $filters (Doc 04 section 4):
     *
     * - search: the name, the Item Number, the SKU, the barcode, the
     *   category's name and, when $withBrands, the brand's.
     * - type: physical, digital, service or combo.
     * - usage: sales, purchase or both - exactly; "sales" leaves out the items
     *   used for both.
     * - category_id, and brand_id when $withBrands.
     * - status: active or inactive.
     * - for_combo: only what a combo can be made of - active, sold, and not a
     *   combo itself.
     */
    public function paginateFor(Workspace $workspace, array $filters, int $perPage, bool $withBrands): LengthAwarePaginator
    {
        return $this->queryFor($workspace, $filters, $withBrands)->paginate($perPage);
    }

    /** How many items the list holds with $filters - for an export. */
    public function countFor(Workspace $workspace, array $filters, bool $withBrands): int
    {
        return $this->queryFor($workspace, $filters, $withBrands)->count();
    }

    /** The list's items with $filters, read 500 at a time - for an export. */
    public function lazyFor(Workspace $workspace, array $filters, bool $withBrands): LazyCollection
    {
        return $this->queryFor($workspace, $filters, $withBrands)->lazy(500);
    }

    /** The business's items named $name, any case - the first $limit of them. */
    public function namedLike(Workspace $workspace, string $name, int $limit): Collection
    {
        return Item::where('workspace_id', $workspace->id)
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
            ->orderBy('id')
            ->limit($limit)
            ->get(['id', 'item_number', 'name']);
    }

    /** The list's items, searched and filtered the same way, in its order - for an export, whole. */
    public function queryFor(Workspace $workspace, array $filters, bool $withBrands): Builder
    {
        return Item::where('workspace_id', $workspace->id)
            ->with(self::SHOWN_WITH)
            ->when($filters['search'] ?? null, fn (Builder $query, string $search) => $query->where(fn (Builder $query) => $this->search($query, $search, $withBrands)))
            ->when($filters['type'] ?? null, fn (Builder $query, string $type) => $query->where('type', $type))
            ->when($filters['usage'] ?? null, fn (Builder $query, string $usage) => $query->where('usage', $usage))
            ->when($filters['category_id'] ?? null, fn (Builder $query, $id) => $query->where('category_id', $id))
            ->when($withBrands && ($filters['brand_id'] ?? null), fn (Builder $query) => $query->where('brand_id', $filters['brand_id']))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('is_active', $status === 'active'))
            ->when(filter_var($filters['for_combo'] ?? false, FILTER_VALIDATE_BOOLEAN), fn (Builder $query) => $query
                ->where('is_active', true)
                ->where('type', '!=', Item::TYPE_COMBO)
                ->where('usage', '!=', Item::USAGE_PURCHASE))
            ->when(filter_var($filters['for_sale'] ?? false, FILTER_VALIDATE_BOOLEAN), fn (Builder $query) => $query
                ->where('is_active', true)
                ->where('usage', '!=', Item::USAGE_PURCHASE))
            ->orderBy('name')
            ->orderBy('id');
    }

    public function findFor(Workspace $workspace, int $id): ?Item
    {
        return $workspace->items()->with(self::SHOWN_WITH)->find($id);
    }

    /** The relations ItemResource shows, read again after a save. */
    public function loadShown(Item $item): Item
    {
        return $item->load(self::SHOWN_WITH);
    }

    /**
     * Made through the business, so its workspace is never read from $data.
     * Its Item Number is given as it is saved (see Item).
     */
    public function create(Workspace $workspace, array $data): Item
    {
        return $workspace->items()->create($data);
    }

    public function update(Item $item, array $data): Item
    {
        $item->update($data);

        return $item;
    }

    /**
     * A combo's components, replaced by $rows ([item_id, quantity] each) in the
     * order given. Empty takes them all away.
     */
    public function replaceComponents(Item $combo, array $rows): void
    {
        ItemComponent::where('combo_id', $combo->id)->delete();

        foreach (array_values($rows) as $index => $row) {
            ItemComponent::create([
                'combo_id'     => $combo->id,
                'component_id' => (int) $row['item_id'],
                'quantity'     => $row['quantity'],
                'sort_order'   => $index,
            ]);
        }
    }

    /** [component id => quantity] - to tell whether a save changed them. */
    public function componentQuantities(Item $combo): array
    {
        return ItemComponent::where('combo_id', $combo->id)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->pluck('quantity', 'component_id')
            ->map(fn ($quantity) => (string) $quantity)
            ->all();
    }

    /** An item of business $workspaceId - none when there is no business. */
    public function findInWorkspace(?int $workspaceId, int $id): ?Item
    {
        return Item::where('workspace_id', $workspaceId)->find($id);
    }

    /** The items among $ids of business $workspaceId. */
    public function findManyInWorkspace(?int $workspaceId, SupportCollection|array $ids): Collection
    {
        return Item::where('workspace_id', $workspaceId)->whereIn('id', $ids)->get();
    }

    /** The items among $ids of business $workspaceId, a combo with its components - for sales lines. */
    public function findManyWithComponents(?int $workspaceId, SupportCollection|array $ids): Collection
    {
        return Item::where('workspace_id', $workspaceId)->whereIn('id', $ids)->with('components.component')->get();
    }

    /** The ids of $combo's components. */
    public function componentIdsOf(Item $combo): array
    {
        return ItemComponent::where('combo_id', $combo->id)->pluck('component_id')->all();
    }

    /** The first combo $item is a component of, with just its id and name - for a message naming it. */
    public function firstComboNamedUsing(Item $item): ?Item
    {
        return $item->usedInCombos()->with('combo:id,name')->first()?->combo;
    }

    /** The first combo $item is a component of, if any. */
    public function firstComboUsing(Item $item): ?Item
    {
        return $item->usedInCombos()->with('combo')->first()?->combo;
    }

    public function delete(Item $item): void
    {
        $item->delete();
    }

    private function search(Builder $query, string $search, bool $withBrands): void
    {
        $like = "%{$search}%";

        $query->where('name', 'like', $like)
            ->orWhere('item_number', 'like', $like)
            ->orWhere('sku', 'like', $like)
            ->orWhere('barcode', 'like', $like)
            ->orWhereHas('category', fn (Builder $query) => $query->where('name', 'like', $like));

        if ($withBrands) {
            $query->orWhereHas('brand', fn (Builder $query) => $query->where('name', 'like', $like));
        }
    }
}
