<?php

namespace App\Services\Api\Items;

use App\Enums\ItemLabel;
use App\Models\User\ItemBrand;
use App\Models\User\ItemCategory;
use App\Models\User\Workspace;
use App\Repositories\User\ItemLabelRepository;
use App\Services\Shared\ActivityLogService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Settings > Items: the business's categories and brands (Doc 04 sections 6
 * and 7). A new one can also be made from the item form, where it is picked.
 *
 * Brands are kept whether or not the business uses them: turning Brand
 * Management off only hides them.
 */
class ItemLabelService
{
    public function __construct(
        protected ItemLabelRepository $itemLabelRepository,
    ) {}

    public function list(Workspace $workspace, ItemLabel $label): Collection
    {
        return $this->itemLabelRepository->allFor($workspace, $label);
    }

    public function create(Workspace $workspace, ItemLabel $label, string $name): ItemCategory|ItemBrand
    {
        $row = $this->itemLabelRepository->create($workspace, $label, $name);

        // Logged here, by name, so LogApiWriteActivity does not add its own
        // vaguer "POST api/v1/item-categories" entry for the same request.
        ActivityLogService::record('created', "Added item {$label->noun()} {$this->quoted($row)}", $row, ['source' => 'api']);

        return $row;
    }

    /** A new name shows on every item that carries it - nothing is copied onto items. */
    public function update(Workspace $workspace, ItemLabel $label, int $id, string $name): ItemCategory|ItemBrand
    {
        $row = $this->findOwned($workspace, $label, $id);

        $this->itemLabelRepository->update($row, $name);

        ActivityLogService::record('updated', "Updated item {$label->noun()} {$this->quoted($row)}", $row, [
            'source' => 'api',
            'fields' => array_keys(array_diff_key($row->getChanges(), ['updated_at' => true])),
        ]);

        return $row;
    }

    /** Deleting one keeps its items, without it. */
    public function delete(Workspace $workspace, ItemLabel $label, int $id): void
    {
        $row = $this->findOwned($workspace, $label, $id);

        DB::transaction(fn () => $this->itemLabelRepository->delete($label, $row));

        ActivityLogService::record('deleted', "Deleted item {$label->noun()} {$this->quoted($row)}", $row, [
            'source' => 'api',
            'items'  => $row->items_count,
        ]);
    }

    /** Another business's answers exactly like one that does not exist. */
    private function findOwned(Workspace $workspace, ItemLabel $label, int $id): ItemCategory|ItemBrand
    {
        return $this->itemLabelRepository->findFor($workspace, $label, $id)
            ?? abort(404, "{$label->label()} not found.");
    }

    /** The name, in quotes and cut down, so the log entry fits its 255-character column. */
    private function quoted(ItemCategory|ItemBrand $row): string
    {
        return '"'.Str::limit($row->name, 100).'"';
    }
}
