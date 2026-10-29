<?php

namespace App\Services\Api\Items;

use App\Models\User\Item;
use App\Models\User\Workspace;
use App\Repositories\User\ItemRepository;
use App\Repositories\User\PurchaseRepository;
use App\Repositories\User\SalesDocumentRepository;
use App\Services\Shared\ActivityLogService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ItemService
{
    public function __construct(
        protected ItemRepository $itemRepository,
        protected SalesDocumentRepository $salesDocumentRepository,
        protected PurchaseRepository $purchaseRepository,
    ) {}

    /** The brand is searched and filtered only while the business uses brands (Doc 04 section 7). */
    public function list(Workspace $workspace, array $filters, int $perPage): LengthAwarePaginator
    {
        return $this->itemRepository->paginateFor($workspace, $filters, $perPage, (bool) $workspace->brands_enabled);
    }

    public function find(Workspace $workspace, int $id): Item
    {
        return $this->findOwned($workspace, $id);
    }

    /** $data is validated - a combo's components included (see SaveItemRequest). */
    public function create(Workspace $workspace, array $data): Item
    {
        $components = Arr::pull($data, 'components');

        $item = DB::transaction(function () use ($workspace, $data, $components) {
            $item = $this->itemRepository->create($workspace, $this->withoutComboFields($data));

            if ($item->isCombo()) {
                $this->itemRepository->replaceComponents($item, $components ?? []);
            }

            return $item;
        });

        // Each write is logged here, by name, so LogApiWriteActivity does not
        // add its own vaguer "POST api/v1/items" entry for the same request.
        ActivityLogService::record('created', "Added item {$this->quoted($item)}", $item, ['source' => 'api']);

        return $this->itemRepository->loadShown($item);
    }

    /**
     * An item from an import - never a combo - already checked, its category
     * and brand found or made. Added without a log entry of its own: the
     * import logs once.
     */
    public function import(Workspace $workspace, array $data): Item
    {
        return $this->itemRepository->create($workspace, $this->withoutComboFields($data));
    }

    /**
     * An item that stops being a combo gives up its components and its combo
     * settings: they describe a package it no longer is. Everything else a
     * form hides - a warranty, a delivery method - is kept.
     */
    public function update(Workspace $workspace, int $id, array $data): Item
    {
        $item       = $this->findOwned($workspace, $id);
        $components = Arr::pull($data, 'components');
        $before     = $this->itemRepository->componentQuantities($item);

        DB::transaction(function () use ($item, $data, $components) {
            $this->itemRepository->update($item, $this->withoutComboFields($data, $item));

            if (! $item->isCombo()) {
                $this->itemRepository->replaceComponents($item, []);
            } elseif ($components !== null) {
                $this->itemRepository->replaceComponents($item, $components);
            }
        });

        $fields = array_keys(array_diff_key($item->getChanges(), ['updated_at' => true]));

        if ($this->itemRepository->componentQuantities($item) !== $before) {
            $fields[] = 'components';
        }

        ActivityLogService::record('updated', "Updated item {$this->quoted($item)}", $item, [
            'source' => 'api',
            'fields' => $fields,
        ]);

        return $this->itemRepository->loadShown($item);
    }

    /**
     * Active: offered for new transactions. Inactive: kept, with its history,
     * but not offered (Doc 04 section 18). A component may be deactivated; its
     * combos keep it, but cannot be used in new sales while it is inactive.
     */
    public function setActive(Workspace $workspace, int $id, bool $active): Item
    {
        $item = $this->findOwned($workspace, $id);

        $this->itemRepository->update($item, ['is_active' => $active]);

        ActivityLogService::record('updated', ($active ? 'Activated' : 'Deactivated')." item {$this->quoted($item)}", $item, [
            'source' => 'api',
            'fields' => $item->wasChanged('is_active') ? ['is_active'] : [],
        ]);

        return $item;
    }

    /**
     * Only an unused item can be deleted (Doc 04 section 18): being part of a
     * combo stops it with a 409 naming the combo, and so does being on a
     * sales document.
     */
    public function delete(Workspace $workspace, int $id): void
    {
        $item  = $this->findOwned($workspace, $id);
        $combo = $this->itemRepository->firstComboUsing($item);

        if ($combo) {
            throw new HttpResponseException(response()->json([
                'message' => "This item is part of the combo {$this->quoted($combo)}. Take it out of the combo first, or deactivate it instead.",
            ], 409));
        }

        if ($this->salesDocumentRepository->usesItem($item)) {
            throw new HttpResponseException(response()->json([
                'message' => 'This item is on sales documents, so it cannot be deleted. Deactivate it instead.',
            ], 409));
        }

        if ($this->purchaseRepository->usesItem($item)) {
            throw new HttpResponseException(response()->json([
                'message' => 'This item is on purchases, so it cannot be deleted. Deactivate it instead.',
            ], 409));
        }

        $this->itemRepository->delete($item);

        ActivityLogService::record('deleted', "Deleted item {$this->quoted($item)}", $item, ['source' => 'api']);
    }

    /**
     * Only a digital product has files to deliver (Doc 04 section 12), so only
     * one takes new files - a 422 on files for any other item. One that stops
     * being digital keeps the files it has.
     */
    public function ensureTakesFiles(Item $item): void
    {
        if ($item->type !== Item::TYPE_DIGITAL) {
            throw ValidationException::withMessages(['files' => 'Only a digital product has files to deliver.']);
        }
    }

    /** Only a combo has a pricing and display method. */
    private function withoutComboFields(array $data, ?Item $item = null): array
    {
        if (($data['type'] ?? $item?->type) !== Item::TYPE_COMBO) {
            $data['combo_pricing'] = null;
            $data['combo_display'] = null;
        }

        return $data;
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
