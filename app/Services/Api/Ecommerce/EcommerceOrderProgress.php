<?php

namespace App\Services\Api\Ecommerce;

use App\Models\User\EcommerceFulfilment;
use App\Models\User\EcommerceOrder;
use App\Models\User\EcommerceOrderLine;
use App\Repositories\User\EcommerceFulfilmentRepository;
use App\Repositories\User\EcommerceOrderRepository;
use Illuminate\Support\Collection;

/**
 * An online order's fulfilment status and status following its shipments,
 * its readiness for pickup and its payment (Doc 19 sections 19.19 and 19.27 -
 * Phase G1), inside the transaction of whatever changed them:
 *
 * - its fulfilment, by its products that ship and its fulfilments not
 *   cancelled: Unfulfilled with none; shipped, Partially Fulfilled until all
 *   of each is sent, then Shipped, and Delivered once each shipment is; for
 *   pickup, Ready for Pickup, then Fulfilled once collected. An order with
 *   nothing that ships keeps what it was placed with (Not Required) - its
 *   digital products and services come with Phase G3;
 * - its status, while it is confirmed or further: Processing once anything
 *   is sent or ready, Completed once it has reached its customer and is paid
 *   - a delivery records no payment (19.27) - and Confirmed again when its
 *   fulfilments are all cancelled. Pending, held or cancelled orders keep
 *   theirs.
 */
class EcommerceOrderProgress
{
    public function __construct(
        protected EcommerceOrderRepository $orders,
        protected EcommerceFulfilmentRepository $fulfilments,
    ) {}

    /** $order's products that ship - physical ones and combos - keyed by line id. */
    public function shippable(EcommerceOrder $order): Collection
    {
        return $order->lines->where('kind', EcommerceOrderLine::PRODUCT)->where('shipped', true)->keyBy('id');
    }

    /** How much of each of $order's products its fulfilments not cancelled hold: [order line id => quantity]. */
    public function sent(EcommerceOrder $order, ?Collection $fulfilments = null): array
    {
        $sent = [];

        foreach (($fulfilments ?? $this->fulfilments->ofOrder($order))->where('status', '!=', EcommerceFulfilment::CANCELLED) as $fulfilment) {
            foreach ($fulfilment->lines as $line) {
                $sent[$line->ecommerce_order_line_id] = bcadd($sent[$line->ecommerce_order_line_id] ?? '0', (string) $line->quantity, 3);
            }
        }

        return $sent;
    }

    /** Inside a transaction, $order locked: its fulfilment status, and its status with it, as they are now. */
    public function follow(EcommerceOrder $order): void
    {
        $fulfilments = $this->fulfilments->ofOrder($order);
        $fulfilment  = $this->fulfilmentOf($order, $fulfilments);
        $status      = $this->statusOf($order, $fulfilment);
        $changes     = array_filter(['fulfilment_status' => $fulfilment, 'status' => $status], fn (string $value, string $key) => $value !== $order->{$key}, ARRAY_FILTER_USE_BOTH);

        if ($changes !== []) {
            $this->orders->update($order, $changes);
        }
    }

    private function fulfilmentOf(EcommerceOrder $order, Collection $fulfilments): string
    {
        $lines  = $this->shippable($order);
        $active = $fulfilments->where('status', '!=', EcommerceFulfilment::CANCELLED);

        if ($lines->isEmpty()) {
            return $order->fulfilment_status;
        }

        if ($active->isEmpty()) {
            return EcommerceOrder::UNFULFILLED;
        }

        if ($order->delivery === EcommerceOrder::PICKUP) {
            return $active->contains('status', EcommerceFulfilment::COLLECTED) ? EcommerceOrder::FULFILLED : EcommerceOrder::READY_FOR_PICKUP;
        }

        $sent = $this->sent($order, $fulfilments);
        $all  = $lines->every(fn (EcommerceOrderLine $line) => bccomp($sent[$line->id] ?? '0', (string) $line->quantity, 3) >= 0);

        return match (true) {
            ! $all                                                                                          => EcommerceOrder::PARTIALLY_FULFILLED,
            $active->every(fn (EcommerceFulfilment $fulfilment) => $fulfilment->status === EcommerceFulfilment::DELIVERED) => EcommerceOrder::DELIVERED,
            default                                                                                         => EcommerceOrder::SHIPPED,
        };
    }

    private function statusOf(EcommerceOrder $order, string $fulfilment): string
    {
        if (! in_array($order->status, [EcommerceOrder::CONFIRMED, EcommerceOrder::PROCESSING, EcommerceOrder::COMPLETED], true) || $fulfilment === EcommerceOrder::NOT_REQUIRED) {
            return $order->status;
        }

        return match (true) {
            in_array($fulfilment, [EcommerceOrder::DELIVERED, EcommerceOrder::FULFILLED], true) && $order->payment_status === EcommerceOrder::PAID => EcommerceOrder::COMPLETED,
            $fulfilment === EcommerceOrder::UNFULFILLED                                                                                         => EcommerceOrder::CONFIRMED,
            default                                                                                                                             => EcommerceOrder::PROCESSING,
        };
    }
}
