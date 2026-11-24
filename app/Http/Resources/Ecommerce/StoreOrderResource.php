<?php

namespace App\Http\Resources\Ecommerce;

use App\Models\User\EcommerceOrder;
use App\Models\User\EcommerceOrderLine;
use App\Services\Documents\DocumentLinks;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * An online order as its customer sees it (Doc 19 Phase D4): its number and
 * when it was placed, its three statuses, the customer's details and
 * addresses as given, how it is delivered (a method with its price and days,
 * quoted after the order or not, or a pickup point with its instructions)
 * and paid (the method's instructions, and the reference given), its
 * products as the store showed them, and its totals. Its own link's key
 * comes only once, when it is placed. From Phase F2, its invoice once the
 * order is confirmed - its number and secure link, while the link works.
 *
 * @property EcommerceOrder $resource
 */
class StoreOrderResource extends JsonResource
{
    public function __construct(EcommerceOrder $order, private ?string $key = null)
    {
        parent::__construct($order);
    }

    public function toArray(Request $request): array
    {
        $order = $this->resource;

        return [
            'number'             => $order->number,
            'key'                => $this->key,
            'placed_at'          => $order->placed_at?->toIso8601String(),
            'status'             => $order->status,
            'payment_status'     => $order->payment_status,
            'fulfilment_status'  => $order->fulfilment_status,
            'invoice'            => ($link = $order->invoice?->activeLink) ? ['number' => $order->invoice->number, 'url' => app(DocumentLinks::class)->url($link)] : null,
            'currency'           => $order->currency,
            'prices_include_tax' => $order->prices_include_tax,
            'customer'           => $order->customer,
            'billing_address'    => $order->billing_address,
            'shipping_address'   => $order->shipping_address,
            'delivery'           => ['kind' => $order->delivery, ...($order->delivery_snapshot ?? [])],
            'payment'            => [
                'name'         => $order->payment_snapshot['name'] ?? null,
                'type'         => $order->payment_snapshot['type'] ?? null,
                'instructions' => $order->payment_snapshot['instructions'] ?? null,
                'reference'    => $order->payment_reference,
            ],
            'note'               => $order->customer_note,
            'lines'              => $order->lines->where('kind', EcommerceOrderLine::PRODUCT)->map(fn (EcommerceOrderLine $line) => [
                'title'      => $line->title,
                'options'    => $line->options ?? [],
                'sku'        => $line->sku,
                'quantity'   => (int) $line->quantity,
                'unit_price' => $line->shown_unit_price,
                'amount'     => $line->shown_amount,
            ])->values(),
            'totals'             => [
                'subtotal'    => $order->subtotal,
                'shipping'    => $order->shipping_total,
                'tax_total'   => $order->tax_total,
                'grand_total' => $order->grand_total,
            ],
        ];
    }
}
