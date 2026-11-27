<?php

namespace App\Services\Api\Ecommerce;

use App\Models\User\EcommerceFulfilment;
use App\Models\User\EcommerceOrder;
use App\Models\User\EcommerceOrderLine;
use App\Repositories\User\EcommerceFulfilmentRepository;
use App\Repositories\User\EcommerceOrderDeliveryRepository;
use App\Repositories\User\EcommerceOrderRepository;
use Illuminate\Support\Collection;

/**
 * An online order's fulfilment status and status following its shipments,
 * its readiness for pickup and its payment (Doc 19 sections 19.19 and 19.27 -
 * Phase G1) - and, from Phase G3a, its digital products delivered and its
 * services performed - inside the transaction of whatever changed them:
 *
 * - what ships, by its fulfilments not cancelled: Unfulfilled with none;
 *   shipped, Partially Fulfilled until all of each is sent, then Shipped, and
 *   Delivered once each shipment is; for pickup, Ready for Pickup, then
 *   Fulfilled once collected;
 * - what does not ship (Phase G3a): Unfulfilled while none of its digital
 *   products and services has reached the customer, Partially Fulfilled while
 *   some has, Fulfilled once all have - or, with things that ship, what those
 *   are at, but Partially Fulfilled while the rest is short of them. An order
 *   with neither keeps what it was placed with (Not Required);
 * - its status, while it is confirmed or further: Processing once anything is
 *   sent, ready, delivered or performed, Completed once it has all reached its
 *   customer and is paid - a delivery records no payment (19.27) - and
 *   Confirmed again when nothing has. Pending, held or cancelled orders keep
 *   theirs.
 */
class EcommerceOrderProgress
{
    private const NONE = 'none';

    private const SOME = 'some';

    private const ALL = 'all';

    public function __construct(
        protected EcommerceOrderRepository $orders,
        protected EcommerceFulfilmentRepository $fulfilments,
        protected EcommerceOrderDeliveryRepository $deliveries,
    ) {}

    /** $order's products that ship - physical ones and combos - keyed by line id. */
    public function shippable(EcommerceOrder $order): Collection
    {
        return $order->lines->where('kind', EcommerceOrderLine::PRODUCT)->where('shipped', true)->keyBy('id');
    }

    /** Phase G3a: $order's products that do not ship - its digital products and services - keyed by line id. */
    public function deliverable(EcommerceOrder $order): Collection
    {
        return $order->lines->where('kind', EcommerceOrderLine::PRODUCT)->where('shipped', false)->keyBy('id');
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
        $fulfilment = $this->fulfilmentOf($order, $this->fulfilments->ofOrder($order), $this->deliveries->ofOrder($order));
        $status     = $this->statusOf($order, $fulfilment);
        $changes    = array_filter(['fulfilment_status' => $fulfilment, 'status' => $status], fn (string $value, string $key) => $value !== $order->{$key}, ARRAY_FILTER_USE_BOTH);

        if ($changes !== []) {
            $this->orders->update($order, $changes);
        }
    }

    private function fulfilmentOf(EcommerceOrder $order, Collection $fulfilments, Collection $deliveries): string
    {
        $shipped = $this->shippedOf($order, $fulfilments);
        $rest    = $this->deliveredOf($order, $deliveries);

        return match (true) {
            $shipped === null && $rest === null => $order->fulfilment_status,
            $shipped === null                   => [self::NONE => EcommerceOrder::UNFULFILLED, self::SOME => EcommerceOrder::PARTIALLY_FULFILLED, self::ALL => EcommerceOrder::FULFILLED][$rest],
            $rest === null                      => $shipped,
            $rest === self::ALL                 => $shipped === EcommerceOrder::UNFULFILLED ? EcommerceOrder::PARTIALLY_FULFILLED : $shipped,
            $rest === self::NONE && $shipped === EcommerceOrder::UNFULFILLED => EcommerceOrder::UNFULFILLED,
            default                             => EcommerceOrder::PARTIALLY_FULFILLED,
        };
    }

    /** What its products that ship are at - null when nothing ships. */
    private function shippedOf(EcommerceOrder $order, Collection $fulfilments): ?string
    {
        $lines  = $this->shippable($order);
        $active = $fulfilments->where('status', '!=', EcommerceFulfilment::CANCELLED);

        if ($lines->isEmpty()) {
            return null;
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

    /** Phase G3a: how many of its digital products and services reached the customer - none, some or all - or null when it has none. */
    private function deliveredOf(EcommerceOrder $order, Collection $deliveries): ?string
    {
        $lines = $this->deliverable($order);

        if ($lines->isEmpty()) {
            return null;
        }

        $done = $lines->filter(fn (EcommerceOrderLine $line) => $deliveries->get($line->id)?->isDone() ?? false)->count();

        return match ($done) {
            0                => self::NONE,
            $lines->count()  => self::ALL,
            default          => self::SOME,
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
