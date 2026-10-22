<?php

namespace App\Repositories\User;

use App\Models\User\Item;
use App\Models\User\Workspace;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Items belong to one business (workspace). Every lookup is scoped to it, so
 * another business's item - the same owner's or anyone else's - is simply not
 * found.
 */
class ItemRepository
{
    /**
     * A page of the business's items, alphabetical with the id as tie-breaker.
     * $filters: search (name or SKU), type (product or service) and status
     * (active or inactive).
     */
    public function paginateFor(Workspace $workspace, array $filters, int $perPage): LengthAwarePaginator
    {
        return Item::where('workspace_id', $workspace->id)
            ->when($filters['search'] ?? null, fn ($query, string $search) => $query->where(fn ($query) => $query
                ->where('name', 'like', "%{$search}%")
                ->orWhere('sku', 'like', "%{$search}%")))
            ->when($filters['type'] ?? null, fn ($query, string $type) => $query->where('type', $type))
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('is_active', $status === 'active'))
            ->orderBy('name')
            ->orderBy('id')
            ->paginate($perPage);
    }

    public function findFor(Workspace $workspace, int $id): ?Item
    {
        return $workspace->items()->find($id);
    }

    /** Made through the business, so its workspace is never read from $data. */
    public function create(Workspace $workspace, array $data): Item
    {
        return $workspace->items()->create($data);
    }

    public function update(Item $item, array $data): Item
    {
        $item->update($data);

        return $item;
    }

    public function delete(Item $item): void
    {
        $item->delete();
    }
}
