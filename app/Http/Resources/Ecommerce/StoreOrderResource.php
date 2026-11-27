<?php

namespace App\Http\Resources\Ecommerce;

use App\Models\User\EcommerceFulfilment;
use App\Models\User\EcommerceFulfilmentLine;
use App\Models\User\EcommerceOrder;
use App\Models\User\EcommerceOrderDelivery;
use App\Models\User\EcommerceOrderLine;
use App\Models\User\WorkspaceFile;
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
 * order is confirmed - its number and secure link, while the link works;
 * from Phase G1, its shipments with their tracking, or its pickup; from
 * Phase G3a, its digital products and services, and what each delivered.
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
            // Phase H1: the discount code it was placed with, while its discount is the code's.
            'discount_code'      => $order->discountCode(),
            'totals'             => [
                'subtotal'    => $order->subtotal,
                'shipping'    => $order->shipping_total,
                // Phase F5b: what a discount given by the business takes off - never why.
                'discount'    => $order->discount_total,
                'tax_total'   => $order->tax_total,
                'grand_total' => $order->grand_total,
            ],
            // Phase G1: its shipments, or its pickup, as they stand - never one cancelled, nor the business's notes.
            'fulfilments'        => $order->fulfilments->where('status', '!=', EcommerceFulfilment::CANCELLED)->map(fn (EcommerceFulfilment $fulfilment) => [
                'kind'            => $fulfilment->kind,
                'sequence'        => $fulfilment->sequence,
                'status'          => $fulfilment->status,
                'fulfilled_on'    => $fulfilment->fulfilled_on?->toDateString(),
                'completed_on'    => $fulfilment->completed_on?->toDateString(),
                'carrier'         => $fulfilment->carrier,
                'tracking_number' => $fulfilment->tracking_number,
                'tracking_url'    => $fulfilment->tracking_url,
                'lines'           => $fulfilment->lines->map(fn (EcommerceFulfilmentLine $line) => [
                    'title'    => $order->lines->firstWhere('id', $line->ecommerce_order_line_id)?->title,
                    'quantity' => (int) $line->quantity,
                ])->values(),
            ])->values(),
            // Phase G3a: its digital products and services, as they stand - a product's keys, the note and its item's
            // instructions only while it is delivered (19.28: nothing protected before it is paid, or once revoked), a
            // service's day and note once performed; never why access was revoked.
            'deliverables'       => $order->lines->where('kind', EcommerceOrderLine::PRODUCT)->where('shipped', false)->map(function (EcommerceOrderLine $line) use ($order) {
                $delivery = $order->deliveries->firstWhere('ecommerce_order_line_id', $line->id);
                $state    = EcommerceOrderDelivery::stateOf($order, $line, $delivery);
                $open     = $state === EcommerceOrderDelivery::DELIVERED;

                return [
                    'title'        => $line->title,
                    'options'      => array_column($line->options ?? [], 'value'),
                    'quantity'     => (int) $line->quantity,
                    'kind'         => $line->isService() ? EcommerceOrderDelivery::SERVICE : EcommerceOrderDelivery::DIGITAL,
                    'method'       => $line->isService() ? null : ($delivery?->method ?? $line->deliveryMethod()),
                    'state'        => $state,
                    'license_keys' => $open ? ($delivery->license_keys ?? []) : [],
                    'note'         => $open || $state === EcommerceOrderDelivery::PERFORMED ? $delivery->note : null,
                    'instructions' => $open ? $line->item?->delivery_instructions : null,
                    'performed_on' => $delivery?->performed_on?->toDateString(),
                    // Phase G3b: a delivered download's files, each with how many more times it may be downloaded (null: no
                    // limit), until when, and whether the customer must be signed in to their account for it.
                    'download'     => $open && $delivery->method === EcommerceOrderDelivery::DOWNLOAD ? [
                        'id'            => $delivery->id,
                        'until'         => $delivery->expires_at?->toIso8601String(),
                        'needs_account' => $order->downloadsNeedAccount,
                        'files'         => $line->digitalFiles()->map(fn (WorkspaceFile $file) => [
                            'id'   => $file->id,
                            'name' => $file->name,
                            'size' => $file->size,
                            'left' => $delivery->leftOf($file->id),
                        ])->values(),
                    ] : null,
                ];
            })->values(),
        ];
    }
}
