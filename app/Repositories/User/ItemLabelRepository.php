<?php

namespace App\Repositories\User;

use App\Enums\ItemLabel;
use App\Models\User\Item;
use App\Models\User\ItemBrand;
use App\Models\User\ItemCategory;
use App\Models\User\Workspace;
use Illuminate\Database\Eloquent\Collection;

/**
 * A business's item categories and brands (see ItemLabel). Every lookup is
 * scoped to the business, so another business's is simply not found.
 */
class ItemLabelRepository
{
    /** The whole list, alphabetical, each with how many items carry it. Not paginated: a business has tens. */
    public function allFor(Workspace $workspace, ItemLabel $label): Collection
    {
        return $label->model()::where('workspace_id', $workspace->id)
            ->withCount('items')
            ->orderBy('name')
            ->orderBy('id')
            ->get();
    }

    public function findFor(Workspace $workspace, ItemLabel $label, int $id): ItemCategory|ItemBrand|null
    {
        return $label->model()::where('workspace_id', $workspace->id)->withCount('items')->find($id);
    }

    public function create(Workspace $workspace, ItemLabel $label, string $name): ItemCategory|ItemBrand
    {
        return $label->model()::create(['workspace_id' => $workspace->id, 'name' => $name])->loadCount('items');
    }

    public function update(ItemCategory|ItemBrand $row, string $name): ItemCategory|ItemBrand
    {
        $row->update(['name' => $name]);

        return $row;
    }

    /** Its items lose it - they are kept, without a category or brand - and then it goes. */
    public function delete(ItemLabel $label, ItemCategory|ItemBrand $row): void
    {
        Item::where('workspace_id', $row->workspace_id)
            ->where($label->itemColumn(), $row->id)
            ->update([$label->itemColumn() => null]);

        $row->delete();
    }
}
