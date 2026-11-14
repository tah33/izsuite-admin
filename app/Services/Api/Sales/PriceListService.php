<?php

namespace App\Services\Api\Sales;

use App\Models\User\PriceList;
use App\Models\User\User;
use App\Models\User\Workspace;
use App\Repositories\User\PriceListRepository;
use App\Services\Shared\ActivityLogService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Price lists kept (Doc 16 section 16.19): made, changed with their rules,
 * customers and locations, switched off, and deleted while no sales line was
 * priced by one. A change reprices nothing already made: each line keeps the
 * price it got (PriceListPricing gives new lines theirs).
 */
class PriceListService
{
    use RefusesConflicts;

    private const FIELDS = ['name', 'currency', 'is_active', 'valid_from', 'valid_to', 'all_customers', 'all_locations', 'priority', 'notes'];

    public function __construct(
        protected PriceListRepository $priceLists,
    ) {}

    public function list(Workspace $workspace, array $filters, int $perPage): LengthAwarePaginator
    {
        return $this->priceLists->paginateFor($workspace, $filters, $perPage);
    }

    public function find(Workspace $workspace, int $id): PriceList
    {
        return $this->priceLists->findFor($workspace, $id) ?? abort(404, 'Price list not found.');
    }

    /** $data is validated (see SavePriceListRequest). */
    public function create(Workspace $workspace, User $user, array $data): PriceList
    {
        $this->checkName($workspace, $data['name']);

        $list = DB::transaction(function () use ($workspace, $user, $data) {
            $list = $this->priceLists->create($workspace, [...Arr::only($data, self::FIELDS), 'created_by' => $user->id, 'updated_by' => $user->id]);

            $this->priceLists->replaceParts($list, ...$this->parts($data));

            return $list;
        });

        ActivityLogService::record('created', "Added price list \"{$list->name}\"", $list, ['source' => 'api']);

        return $this->priceLists->loadShown($list);
    }

    public function update(Workspace $workspace, int $id, User $user, array $data): PriceList
    {
        $list = $this->find($workspace, $id);

        $this->checkName($workspace, $data['name'], $list->id);

        DB::transaction(function () use ($list, $user, $data) {
            $this->priceLists->update($list, [...Arr::only($data, self::FIELDS), 'updated_by' => $user->id]);
            $this->priceLists->replaceParts($list, ...$this->parts($data));
        });

        ActivityLogService::record('updated', "Updated price list \"{$list->name}\"", $list, ['source' => 'api']);

        return $this->priceLists->loadShown($list->refresh());
    }

    /** Deleted only while nothing was priced by it - otherwise it is switched off, so the lines keep naming it. */
    public function delete(Workspace $workspace, int $id): void
    {
        $list = $this->find($workspace, $id);

        if ($this->priceLists->isUsed($list)) {
            $this->refuse('Sales lines were priced by this list. Switch it off instead, so they keep naming it.');
        }

        $this->priceLists->delete($list);

        ActivityLogService::record('deleted', "Deleted price list \"{$list->name}\"", $list, ['source' => 'api']);
    }

    private function checkName(Workspace $workspace, string $name, ?int $except = null): void
    {
        if ($this->priceLists->nameTaken($workspace, trim($name), $except)) {
            throw ValidationException::withMessages(['name' => 'Another price list has this name.']);
        }
    }

    /** [its rules, its customers' ids, its locations' ids] - none when it is for all of them. */
    private function parts(array $data): array
    {
        $rules = array_values(array_map(fn (array $rule, int $index) => [
            'target'           => $rule['target'],
            'item_id'          => $rule['target'] === 'item' ? $rule['item_id'] : null,
            'item_category_id' => $rule['target'] === 'category' ? $rule['item_category_id'] : null,
            'item_brand_id'    => $rule['target'] === 'brand' ? $rule['item_brand_id'] : null,
            'min_quantity'     => $rule['min_quantity'] ?? '1',
            'method'           => $rule['method'],
            'value'            => (string) $rule['value'],
            'valid_from'       => $rule['valid_from'] ?? null,
            'valid_to'         => $rule['valid_to'] ?? null,
            'position'         => $index + 1,
        ], $data['rules'] ?? [], array_keys($data['rules'] ?? [])));

        return [
            $rules,
            ($data['all_customers'] ?? true) ? [] : array_map('intval', $data['contact_ids'] ?? []),
            ($data['all_locations'] ?? true) ? [] : array_map('intval', $data['location_ids'] ?? []),
        ];
    }
}
