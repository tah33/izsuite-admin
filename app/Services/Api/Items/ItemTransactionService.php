<?php

namespace App\Services\Api\Items;

use App\Enums\DocumentType;
use App\Models\User\Workspace;
use App\Repositories\User\ItemRepository;
use App\Repositories\User\ItemTransactionRepository;
use App\Services\Shared\BusinessCalendar;
use App\Services\Shared\WorkspaceAccess;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * An item's related transactions on its profile (Doc 04 sections 16 and 17):
 * each document line that holds it, as it was kept - read only, each opening
 * its document. Another business's item is a 404. Only documents of the
 * kinds the user's role may view, at the locations they may use, are among
 * them (Doc 12).
 */
class ItemTransactionService
{
    public function __construct(
        protected ItemRepository $itemRepository,
        protected ItemTransactionRepository $itemTransactionRepository,
        protected BusinessCalendar $businessCalendar,
        protected WorkspaceAccess $access,
    ) {}

    public function list(Workspace $workspace, int $itemId, int $perPage): LengthAwarePaginator
    {
        $item  = $this->itemRepository->findFor($workspace, $itemId) ?? abort(404, 'Item not found.');
        $types = $this->access->viewable([...array_values(DocumentType::KINDS), DocumentType::Purchase]);

        return $this->itemTransactionRepository->paginateFor($workspace, $item, $perPage, $types, $this->access->locations());
    }

    /** The business's today, which a document's status is read against. */
    public function today(Workspace $workspace): string
    {
        return $this->businessCalendar->today($workspace)->toDateString();
    }
}
