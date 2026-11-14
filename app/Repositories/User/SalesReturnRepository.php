<?php

namespace App\Repositories\User;

use App\Models\User\SalesDocument;
use App\Models\User\SalesReturn;
use App\Models\User\SalesReturnLine;
use App\Models\User\Workspace;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Customer returns (Doc 16 Phase E) belong to one business: every lookup is
 * scoped to it, so another business's return is simply not found.
 */
class SalesReturnRepository
{
    /** What a return's page shows with it. */
    private const SHOWN_WITH = [
        'lines.orderLine:id,sales_document_id,item_id,name,unit,quantity,delivered_quantity,returned_quantity',
        'order', 'order.contact', 'delivery:id,number', 'location:id,name', 'creator:id,first_name,last_name',
    ];

    /** The Returns page, newest first: searched by number, order, delivery or customer, filtered by status and dates. */
    public function paginateFor(Workspace $workspace, array $filters, int $perPage): LengthAwarePaginator
    {
        return SalesReturn::query()
            ->where('workspace_id', $workspace->id)
            ->with(['order:id,number,contact_id,customer_snapshot', 'order.contact:id,name', 'delivery:id,number', 'location:id,name'])
            ->when($filters['search'] ?? null, fn (Builder $query, string $search) => $query->where(fn (Builder $query) => $query
                ->where('number', 'like', "%{$search}%")
                ->orWhereHas('delivery', fn (Builder $query) => $query->where('number', 'like', "%{$search}%"))
                ->orWhereHas('order', fn (Builder $query) => $query->where('number', 'like', "%{$search}%")
                    ->orWhereHas('contact', fn (Builder $query) => $query->where('name', 'like', "%{$search}%")))))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['location_ids'] ?? null, fn (Builder $query, array $ids) => $query->whereIn('location_id', $ids))
            ->when($filters['date_from'] ?? null, fn (Builder $query, string $date) => $query->where('return_date', '>=', $date))
            ->when($filters['date_to'] ?? null, fn (Builder $query, string $date) => $query->where('return_date', '<=', $date))
            ->orderByDesc('return_date')
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    public function findFor(Workspace $workspace, int $id): ?SalesReturn
    {
        return SalesReturn::query()->where('workspace_id', $workspace->id)->with(self::SHOWN_WITH)->find($id);
    }

    /** Read again after a change, with what its page shows. */
    public function loadShown(SalesReturn $return): SalesReturn
    {
        return $return->load(self::SHOWN_WITH);
    }

    /** A sales order's returns, oldest first, for its page. */
    public function forOrder(SalesDocument $order): Collection
    {
        return SalesReturn::query()
            ->where('sales_document_id', $order->id)
            ->orderBy('id')
            ->get(['id', 'sales_document_id', 'number', 'status', 'return_date']);
    }

    public function create(Workspace $workspace, array $data, array $lines): SalesReturn
    {
        // Made for the business, so its workspace is never read from $data.
        $return = new SalesReturn($data);
        $return->workspace_id = $workspace->id;
        $return->save();

        $return->lines()->createMany($lines);

        return $return;
    }

    public function update(SalesReturn $return, array $data): SalesReturn
    {
        $return->update($data);

        return $return;
    }

    public function replaceLines(SalesReturn $return, array $lines): void
    {
        $return->lines()->delete();
        $return->lines()->createMany($lines);
        $return->unsetRelation('lines');
        $return->touch();
    }

    public function delete(SalesReturn $return): void
    {
        $return->delete();
    }

    public function numberTaken(Workspace $workspace, string $number): bool
    {
        return SalesReturn::query()->where('workspace_id', $workspace->id)->where('number', $number)->exists();
    }

    /**
     * How much of each of $lineIds returns in $statuses hold - but $except's -
     * by order line id.
     *
     * @return array<int, string>
     */
    public function quantitiesOf(array $lineIds, array $statuses, ?int $except = null): array
    {
        return $lineIds === [] ? [] : SalesReturnLine::query()
            ->whereIn('sales_document_line_id', $lineIds)
            ->whereHas('salesReturn', fn (Builder $query) => $query->whereIn('status', $statuses)->when($except, fn (Builder $query, int $id) => $query->whereKeyNot($id)))
            ->groupBy('sales_document_line_id')
            ->selectRaw('sales_document_line_id, sum(quantity) as total')
            ->pluck('total', 'sales_document_line_id')
            ->map(fn ($total) => (string) $total)
            ->all();
    }
}
