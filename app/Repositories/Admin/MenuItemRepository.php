<?php

namespace App\Repositories\Admin;

use App\Models\Frontend\MenuItem;
use Illuminate\Database\Eloquent\Collection;

class MenuItemRepository
{
    /** Every menu item, in sidebar order. */
    public function allOrdered(): Collection
    {
        return MenuItem::orderBy('sort_order')->orderBy('id')->get();
    }

    /** The items switched on, in sidebar order - what a business's sidebar is built from. */
    public function activeOrdered(): Collection
    {
        return MenuItem::where('is_active', true)->orderBy('sort_order')->orderBy('id')->get();
    }

    /** The highest sort order in use, so a new item goes last. */
    public function maxSortOrder(): int
    {
        return (int) MenuItem::max('sort_order');
    }

    /** The top-level items $item can sit under - itself left out. */
    public function possibleParents(MenuItem $item): Collection
    {
        return MenuItem::whereNull('parent_id')->whereKeyNot($item->id ?? 0)->orderBy('section')->orderBy('sort_order')->get();
    }

    public function find(int $id): ?MenuItem
    {
        return MenuItem::find($id);
    }

    public function findOrFail(int $id): MenuItem
    {
        return MenuItem::findOrFail($id);
    }

    public function create(array $data): MenuItem
    {
        return MenuItem::create($data);
    }

    public function update(MenuItem $item, array $data): MenuItem
    {
        $item->update($data);

        return $item;
    }

    public function delete(MenuItem $item): ?bool
    {
        return $item->delete();
    }

    /** Moves item $id to $sortOrder - a query update, so no model events run. */
    public function setSortOrder(int $id, int $sortOrder): void
    {
        MenuItem::whereKey($id)->update(['sort_order' => $sortOrder]);
    }
}
