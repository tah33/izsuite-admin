<?php

namespace App\Http\Resources\Sales;

use App\Models\User\SalesDelivery;
use App\Models\User\SalesDeliveryLine;
use App\Services\Api\Sales\SalesDeliveryService;
use App\Services\Api\Sales\SalesSnapshots;
use App\Services\Sales\SalesDeliveryRules;
use App\Services\Shared\AllowedActions;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A delivery note, whole, for its page and form (Doc 16 Phase C): its order
 * and customer, where it goes - its own address, else the order's - who
 * carries and received it, its lines, and the actions its status allows. While
 * it can be changed, each of the order's lines with what is left to deliver.
 * Expects lines.orderLine, order.contact, location, backorderOf and creator.
 */
class SalesDeliveryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var SalesDelivery $delivery */
        $delivery = $this->resource;
        $order    = $delivery->order;
        $actions  = app(SalesDeliveryRules::class)->actions($delivery);
        $customer = $order->customer_snapshot ?? ($order->contact ? app(SalesSnapshots::class)->customerOf($order) : null);

        return [
            'id'               => $delivery->id,
            'number'           => $delivery->number,
            'status'           => $delivery->status,
            'actions'          => app(AllowedActions::class)->deliveryNote($actions),
            'order'            => ['id' => $order->id, 'number' => $order->number, 'status' => $order->status->value],
            'customer'         => $customer['name'] ?? null,
            'delivery_date'    => $delivery->delivery_date,
            'location_id'      => $delivery->location_id,
            'location'         => $delivery->location?->name,
            'shipping_address' => $delivery->shipping_address,
            'ship_to'          => $delivery->shipping_address
                ? app(SalesSnapshots::class)->addressLines($delivery->shipping_address)
                : ($customer['shipping_address'] ?? $customer['billing_address'] ?? []),
            'carrier'          => $delivery->carrier,
            'tracking_number'  => $delivery->tracking_number,
            'vehicle'          => $delivery->vehicle,
            'recipient_name'   => $delivery->recipient_name,
            'notes'            => $delivery->notes,
            'delivered_on'     => $delivery->delivered_on,
            'status_reason'    => $delivery->status_reason,
            'backorder_of'     => $delivery->backorderOf ? ['id' => $delivery->backorderOf->id, 'number' => $delivery->backorderOf->number] : null,
            'lines'            => $delivery->lines->map(fn (SalesDeliveryLine $line) => [
                'line_id'  => $line->sales_document_line_id,
                'name'     => $line->orderLine?->name,
                'unit'     => $line->orderLine?->unit,
                'quantity' => $line->quantity,
            ])->all(),
            // What its form offers: each order line, with what is left of it besides this delivery.
            'available'        => in_array('edit', $actions, true)
                ? collect(app(SalesDeliveryService::class)->left($order, $delivery->id))->map(fn (array $row, int $id) => ['line_id' => $id, ...$row])->values()->all()
                : null,
            'made_by'          => $delivery->creator?->name,
        ];
    }
}
