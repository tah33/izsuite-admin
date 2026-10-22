<?php

namespace App\Services\Api\Items;

use App\Models\User\Item;
use App\Models\User\Workspace;
use App\Repositories\User\ItemRepository;
use App\Services\Shared\ActivityLogService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;

class ItemService
{
    public function __construct(
        protected ItemRepository $itemRepository,
    ) {}

    public function list(Workspace $workspace, array $filters, int $perPage): LengthAwarePaginator
    {
        return $this->itemRepository->paginateFor($workspace, $filters, $perPage);
    }

    public function create(Workspace $workspace, array $data): Item
    {
        $item = $this->itemRepository->create($workspace, $data);

        // Each write is logged here, by name, so LogApiWriteActivity does not
        // add its own vaguer "POST api/v1/items" entry for the same request.
        ActivityLogService::record('created', "Added item {$this->quoted($item)}", $item, ['source' => 'api']);

        return $item;
    }

    public function update(Workspace $workspace, int $id, array $data): Item
    {
        $item = $this->findOwned($workspace, $id);

        $this->itemRepository->update($item, $data);

        ActivityLogService::record('updated', "Updated item {$this->quoted($item)}", $item, [
            'source' => 'api',
            'fields' => array_keys(array_diff_key($item->getChanges(), ['updated_at' => true])),
        ]);

        return $item;
    }

    /**
     * Nothing points at an item yet. When documents do, one that is in use
     * must be made inactive instead of deleted.
     */
    public function delete(Workspace $workspace, int $id): void
    {
        $item = $this->findOwned($workspace, $id);

        $this->itemRepository->delete($item);

        ActivityLogService::record('deleted', "Deleted item {$this->quoted($item)}", $item, ['source' => 'api']);
    }

    /**
     * An item of another business - the owner's own other one, or someone
     * else's - answers exactly like one that does not exist.
     */
    private function findOwned(Workspace $workspace, int $id): Item
    {
        return $this->itemRepository->findFor($workspace, $id)
            ?? abort(404, 'Item not found.');
    }

    /** The name, in quotes and cut down, so the log entry fits its 255-character column. */
    private function quoted(Item $item): string
    {
        return '"'.Str::limit($item->name, 100).'"';
    }
}
