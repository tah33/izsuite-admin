<?php

namespace App\Repositories\User;

use App\Models\User\Item;
use App\Models\User\PriceList;
use App\Models\User\SalesDocumentLine;
use App\Models\User\Workspace;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Arr;

/**
 * Price lists (Doc 16 Phase F1) belong to one business: every lookup is scoped
 * to it, so another business's list is simply not found.
 */
class PriceListRepository
{
    private const SHOWN_WITH = ['rules.item:id,name', 'rules.category:id,name', 'rules.brand:id,name', 'contacts:id,name', 'locations:id,name'];

    /** The Price Lists page, by priority then name: searched by name, filtered active or not. */
    public function paginateFor(Workspace $workspace, array $filters, int $perPage): LengthAwarePaginator
    {
        return PriceList::query()
            ->where('workspace_id', $workspace->id)
            ->withCount('rules')
            ->when($filters['search'] ?? null, fn (Builder $query, string $search) => $query->where('name', 'like', "%{$search}%"))
            ->when(isset($filters['status']), fn (Builder $query) => $query->where('is_active', $filters['status'] === 'active'))
            ->orderBy('priority')
            ->orderBy('name')
            ->paginate($perPage);
    }

    /**
     * Its active price lists by name, each with its currency and - when not
     * for every location - its locations: what a POS register may sell with
     * (Doc 18 Phase A2).
     */
    public function activeFor(Workspace $workspace): \Illuminate\Database\Eloquent\Collection
    {
        return PriceList::query()->where('workspace_id', $workspace->id)->where('is_active', true)->with('locations:id')->orderBy('name')->get(['id', 'name', 'currency', 'all_locations']);
    }

    public function findFor(Workspace $workspace, int $id): ?PriceList
    {
        return PriceList::query()->where('workspace_id', $workspace->id)->with(self::SHOWN_WITH)->find($id);
    }

    public function loadShown(PriceList $list): PriceList
    {
        return $list->load(self::SHOWN_WITH);
    }

    /** Made for the business, so its workspace is never read from $data. */
    public function create(Workspace $workspace, array $data): PriceList
    {
        $list = new PriceList($data);
        $list->workspace_id = $workspace->id;
        $list->save();

        return $list;
    }

    public function update(PriceList $list, array $data): PriceList
    {
        $list->update($data);

        return $list;
    }

    /**
     * Its rules, customers and locations as given. A rule sent with its id is
     * changed where it is, so the sales lines it priced keep it (Doc 16 Phase
     * H2b); another is added, and the list's others go.
     */
    public function replaceParts(PriceList $list, array $rules, array $contactIds, array $locationIds): void
    {
        $kept = $list->rules()->whereIn('id', array_filter(array_column($rules, 'id')))->pluck('id')->all();
        $used = [];

        $list->rules()->whereNotIn('id', $kept)->delete();

        foreach ($rules as $rule) {
            $id     = $rule['id'] ?? null;
            $fields = Arr::except($rule, 'id');

            // An id sent twice is the first one's; the second is added.
            if (in_array($id, $kept, true) && ! isset($used[$id])) {
                $used[$id] = true;
                $list->rules()->whereKey($id)->update($fields);
            } else {
                $list->rules()->create($fields);
            }
        }

        $list->contacts()->sync($contactIds);
        $list->locations()->sync($locationIds);
        $list->unsetRelations();
    }

    public function delete(PriceList $list): void
    {
        $list->delete();
    }

    public function nameTaken(Workspace $workspace, string $name, ?int $except = null): bool
    {
        return PriceList::query()->where('workspace_id', $workspace->id)->where('name', $name)->when($except, fn (Builder $query, int $id) => $query->whereKeyNot($id))->exists();
    }

    /** Whether any sales line was priced by it: then it is switched off, never deleted. */
    public function isUsed(PriceList $list): bool
    {
        return SalesDocumentLine::query()->where('price_list_id', $list->id)->exists();
    }

    /**
     * The active lists that hold for a line on $date in $currency - for
     * $contactId's customer and at $locationId when they are chosen ones -
     * lowest priority number first, with their rules.
     */
    public function eligible(Workspace $workspace, string $currency, string $date, ?int $contactId, ?int $locationId): Collection
    {
        return PriceList::query()
            ->where('workspace_id', $workspace->id)
            ->where('is_active', true)
            ->where('currency', $currency)
            ->where(fn (Builder $query) => $query->whereNull('valid_from')->orWhere('valid_from', '<=', $date))
            ->where(fn (Builder $query) => $query->whereNull('valid_to')->orWhere('valid_to', '>=', $date))
            ->where(fn (Builder $query) => $query->where('all_customers', true)->orWhereHas('contacts', fn (Builder $query) => $query->whereKey($contactId ?? 0)))
            ->where(fn (Builder $query) => $query->where('all_locations', true)->orWhereHas('locations', fn (Builder $query) => $query->whereKey($locationId ?? 0)))
            ->with('rules')
            ->orderBy('priority')
            ->orderBy('id')
            ->get();
    }

    /**
     * The business's default list (Doc 16 Phase H1), $id, while it holds -
     * active, in $currency, on $date - for any customer and location: tried
     * after the eligible ones. Null without one.
     */
    public function fallback(Workspace $workspace, ?int $id, string $currency, string $date): ?PriceList
    {
        if (! $id) {
            return null;
        }

        return PriceList::query()
            ->where('workspace_id', $workspace->id)
            ->whereKey($id)
            ->where('is_active', true)
            ->where('currency', $currency)
            ->where(fn (Builder $query) => $query->whereNull('valid_from')->orWhere('valid_from', '<=', $date))
            ->where(fn (Builder $query) => $query->whereNull('valid_to')->orWhere('valid_to', '>=', $date))
            ->with('rules')
            ->first();
    }

    /** Rules by id, as a form's lines name them: SalesPricingChecks works out what each makes a line cost. */
    public function rulesFor(array $ids): Collection
    {
        return \App\Models\User\PriceListRule::query()->whereIn('id', $ids)->get();
    }

    /** The business's $ids items, with what the rules match and price them by. */
    public function itemsFor(Workspace $workspace, array $ids): Collection
    {
        return Item::query()->where('workspace_id', $workspace->id)->whereIn('id', $ids)->get(['id', 'category_id', 'brand_id', 'sale_price', 'purchase_price']);
    }
}
