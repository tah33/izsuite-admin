<?php

namespace App\Services\Api\Items;

use App\Models\User\Workspace;
use App\Repositories\User\ItemRepository;
use App\Repositories\User\ItemTransactionRepository;
use App\Services\Shared\BusinessCalendar;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * An item's related transactions on its profile (Doc 04 sections 16 and 17):
 * each document line that holds it, as it was kept - read only, each opening
 * its document. Another business's item is a 404.
 */
class ItemTransactionService
{
    public function __construct(
        protected ItemRepository $itemRepository,
        protected ItemTransactionRepository $itemTransactionRepository,
        protected BusinessCalendar $businessCalendar,
    ) {}

    public function list(Workspace $workspace, int $itemId, int $perPage): LengthAwarePaginator
    {
        $item = $this->itemRepository->findFor($workspace, $itemId) ?? abort(404, 'Item not found.');

        return $this->itemTransactionRepository->paginateFor($workspace, $item, $perPage);
    }

    /** The business's today, which a document's status is read against. */
    public function today(Workspace $workspace): string
    {
        return $this->businessCalendar->today($workspace)->toDateString();
    }
}
