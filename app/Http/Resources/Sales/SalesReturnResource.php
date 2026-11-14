<?php

namespace App\Http\Resources\Sales;

use App\Models\User\SalesReturnLine;
use App\Services\Api\Sales\SalesReturnService;
use App\Services\Api\Sales\SalesSnapshots;
use App\Services\Sales\SalesReturnRules;
use App\Services\Shared\AllowedActions;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A customer return's page (Doc 16 Phase E): its status and what it can do
 * now, the order and delivery it came from, its customer, why and in what
 * condition it comes back, what is done about it, and its lines with the
 * item's warranty - and, while its form is open, what each order line has
 * left to return. A return shows no values.
 */
class SalesReturnResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $return   = $this->resource;
        $order    = $return->order;
        $actions  = app(SalesReturnRules::class)->actions($return);
        $customer = $order->customer_snapshot ?? ($order->contact ? app(SalesSnapshots::class)->customerOf($order) : null);

        return [
            'id'            => $return->id,
            'number'        => $return->number,
            'status'        => $return->status,
            'actions'       => app(AllowedActions::class)->salesReturn($actions),
            'order'         => ['id' => $order->id, 'number' => $order->number],
            'delivery'      => $return->delivery ? ['id' => $return->delivery->id, 'number' => $return->delivery->number] : null,
            'customer'      => $customer['name'] ?? null,
            'return_date'   => $return->return_date,
            'location_id'   => $return->location_id,
            'location'      => $return->location?->name,
            'reason'        => $return->reason,
            'condition'     => $return->condition,
            'resolution'    => $return->resolution,
            'notes'         => $return->notes,
            'status_reason' => $return->status_reason,
            'received_on'   => $return->received_on,
            'lines'         => $return->lines->map(fn (SalesReturnLine $line) => [
                'line_id'  => $line->sales_document_line_id,
                'name'     => $line->orderLine?->name,
                'unit'     => $line->orderLine?->unit,
                'quantity' => $line->quantity,
                'warranty' => $line->warranty,
            ])->all(),
            // What its form offers: each delivered order line, with what is left of it besides this return.
            'available'     => in_array('edit', $actions, true)
                ? collect(app(SalesReturnService::class)->left($order, $return->id, $return->delivery))->map(fn (array $row, int $id) => ['line_id' => $id, ...$row])->values()->all()
                : null,
            'made_by'       => $return->creator?->name,
        ];
    }
}