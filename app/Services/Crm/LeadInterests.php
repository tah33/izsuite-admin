<?php

namespace App\Services\Crm;

use App\Models\User\CrmLead;
use App\Models\User\CrmOpportunity;
use App\Models\User\Workspace;
use App\Repositories\User\ItemRepository;
use App\Support\Decimal;
use Illuminate\Validation\ValidationException;

/**
 * The items or services a lead is interested in (Doc 17 section 17.6 - Phase
 * B2a), as its form sends them: one of the business's items, kept under its
 * name as it is now, or a name typed in - each with a quantity, or none.
 */
class LeadInterests
{
    public function __construct(
        protected ItemRepository $items,
    ) {}

    /**
     * $sent as rows to keep, [item_id, name, quantity] each - an item the
     * business does not have refused.
     *
     * @param  list<array{item_id?: int|null, name?: string|null, quantity?: string|null}>  $sent
     * @return list<array{item_id: int|null, name: string, quantity: string|null}>
     */
    public function rows(Workspace $workspace, array $sent): array
    {
        $ids   = collect($sent)->pluck('item_id')->filter()->map(fn ($id) => (int) $id)->unique()->values();
        $known = $this->items->findManyInWorkspace($workspace->id, $ids)->keyBy('id');
        $rows  = [];

        foreach ($sent as $index => $row) {
            $id = empty($row['item_id']) ? null : (int) $row['item_id'];

            if ($id !== null && ! $known->has($id)) {
                throw ValidationException::withMessages(["interested_items.{$index}.item_id" => 'Choose one of your items, or type its name.']);
            }

            $rows[] = [
                'item_id'  => $id,
                'name'     => $id !== null ? $known[$id]->name : trim((string) $row['name']),
                'quantity' => isset($row['quantity']) && $row['quantity'] !== '' ? Decimal::round(Decimal::of($row['quantity']), 3) : null,
            ];
        }

        return $rows;
    }

    /** Whether $rows differ from what $lead - or an opportunity (Phase D1) - has: the log names the change. */
    public function changed(CrmLead|CrmOpportunity $lead, array $rows): bool
    {
        $had = $lead->interestedItems->map(fn ($item) => [$item->item_id, $item->name, $item->quantity === null ? null : (string) $item->quantity])->all();

        return $had !== array_map(fn (array $row) => [$row['item_id'], $row['name'], $row['quantity']], $rows);
    }
}
