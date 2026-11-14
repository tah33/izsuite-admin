<?php

namespace App\Repositories\User;

use App\Models\User\SalesDelivery;
use App\Models\User\SalesDeliveryLine;
use App\Models\User\SalesDocument;
use App\Models\User\Workspace;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Sales orders' delivery notes (Doc 16 Phase C). Every lookup is the
 * business's own; another business's delivery answers like one that does not
 * exist.
 */
class SalesDeliveryRepository
{
    /** What a delivery's page shows. */
    private const SHOWN_WITH = [
        'lines.orderLine:id,sales_document_id,item_id,name,unit,quantity,delivered_quantity',
        'order',
        'order.contact', 'location:id,name', 'backorderOf:id,number', 'creator:id,first_name,last_name',
    ];

    public function paginateFor(Workspace $workspace, array $filters, int $perPage): LengthAwarePaginator
    {
        return $workspace->salesDeliveries()
            ->with(['order:id,number,contact_id,customer_snapshot', 'order.contact:id,name', 'location:id,name'])
            ->when($filters['search'] ?? null, fn (Builder $query, string $search) => $query->where(fn (Builder $query) => $query
                ->where('number', 'like', "%{$search}%")
                ->orWhere('tracking_number', 'like', "%{$search}%")
                ->orWhereHas('order', fn (Builder $query) => $query->where('number', 'like', "%{$search}%")
                    ->orWhereHas('contact', fn (Builder $query) => $query->where('name', 'like', "%{$search}%")))))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['location_ids'] ?? null, fn (Builder $query, array $ids) => $query->whereIn('location_id', $ids))
            ->when($filters['date_from'] ?? null, fn (Builder $query, string $date) => $query->where('delivery_date', '>=', $date))
            ->when($filters['date_to'] ?? null, fn (Builder $query, string $date) => $query->where('delivery_date', '<=', $date))
            ->orderByDesc('delivery_date')
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    public function findFor(Workspace $workspace, int $id): ?SalesDelivery
    {
        return $workspace->salesDeliveries()->with(self::SHOWN_WITH)->find($id);
    }

    /** Read again after a change, with what its page shows. */
    public function loadShown(SalesDelivery $delivery): SalesDelivery
    {
        return $delivery->load(self::SHOWN_WITH);
    }

    /** An order's deliveries, for its page. */
    public function forOrder(SalesDocument $order): Collection
    {
        return $order->deliveries()->get(['id', 'sales_document_id', 'number', 'status', 'delivery_date', 'delivered_on']);
    }

    public function create(Workspace $workspace, array $data, array $lines): SalesDelivery
    {
        $delivery = $workspace->salesDeliveries()->create($data);

        $delivery->lines()->createMany($lines);

        return $delivery;
    }

    public function update(SalesDelivery $delivery, array $data): SalesDelivery
    {
        $delivery->update($data);

        return $delivery;
    }

    /** Its lines become $lines - order line id and quantity each. */
    public function replaceLines(SalesDelivery $delivery, array $lines): void
    {
        $delivery->lines()->delete();
        $delivery->lines()->createMany($lines);
        $delivery->unsetRelation('lines');
        $delivery->touch();
    }

    public function delete(SalesDelivery $delivery): void
    {
        $delivery->delete();
    }

    public function numberTaken(Workspace $workspace, string $number): bool
    {
        return $workspace->salesDeliveries()->where('number', $number)->exists();
    }

    /**
     * How much of each of $lineIds the deliveries in $statuses hold, by order
     * line id - delivered ones, or the open ones (draft and ready) but $except.
     *
     * @return array<int, string>
     */
    public function quantitiesOf(array $lineIds, array $statuses, ?int $except = null): array
    {
        return $lineIds === [] ? [] : SalesDeliveryLine::query()
            ->whereIn('sales_document_line_id', $lineIds)
            ->whereHas('delivery', fn (Builder $query) => $query->whereIn('status', $statuses)->when($except, fn (Builder $query, int $id) => $query->whereKeyNot($id)))
            ->groupBy('sales_document_line_id')
            ->selectRaw('sales_document_line_id, sum(quantity) as total')
            ->pluck('total', 'sales_document_line_id')
            ->map(fn ($total) => (string) $total)
            ->all();
    }

    /** Whether $order has deliveries not cancelled. */
    public function hasActiveFor(SalesDocument $order): bool
    {
        return $order->deliveries()->where('status', '!=', SalesDelivery::CANCELLED)->exists();
    }

    /** Whether any of an order's $lineIds is on a delivery not cancelled. */
    public function linesOnDeliveries(array $lineIds): bool
    {
        return $lineIds !== [] && SalesDeliveryLine::query()
            ->whereIn('sales_document_line_id', $lineIds)
            ->whereHas('delivery', fn (Builder $query) => $query->where('status', '!=', SalesDelivery::CANCELLED))
            ->exists();
    }
}
