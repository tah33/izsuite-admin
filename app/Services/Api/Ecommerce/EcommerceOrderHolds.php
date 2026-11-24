<?php

namespace App\Services\Api\Ecommerce;

use App\Models\User\EcommerceOrder;
use App\Models\User\EcommerceSetting;
use App\Models\User\Workspace;
use App\Repositories\User\EcommerceOrderRepository;
use App\Services\Api\Sales\RefusesConflicts;
use App\Services\Shared\ActivityLogService;
use Illuminate\Support\Facades\DB;

/**
 * Holding an online order for review, and releasing it (Doc 19 section 19.24
 * - Phase F4), each with a reason in its timeline:
 *
 * - hold(), under Place Orders On Hold: a pending or confirmed order not yet
 *   fulfilled is On Hold - not confirmed, paid or fulfilled meanwhile -
 *   keeping the status it goes back to;
 * - release(), under the same: back to that status;
 * - holdAsPlaced(): with Settings → eCommerce's Hold Orders for Review on, an
 *   order that may want a look (EcommerceOrderReview) is held as it is
 *   placed - by izSuite, the reason being what wants the look.
 *
 * Its customer sees their order On hold - never why.
 */
class EcommerceOrderHolds
{
    use RefusesConflicts;

    public function __construct(
        protected EcommerceOrderService $orderService,
        protected EcommerceOrderRepository $orders,
        protected EcommerceOrderReview $review,
    ) {}

    public function hold(Workspace $workspace, int $id, string $reason): EcommerceOrder
    {
        $found = $this->orderService->find($workspace, $id);

        DB::transaction(function () use ($found, $reason) {
            $order = $this->orders->forConfirming($found, true);

            if (! in_array($order->status, [EcommerceOrder::PENDING, EcommerceOrder::CONFIRMED], true)) {
                $this->refuse($order->status === EcommerceOrder::ON_HOLD
                    ? "Order {$order->number} is on hold already."
                    : "Only a pending or confirmed order is put on hold: {$order->number} is ".str_replace('_', ' ', $order->status).'.');
            }

            if (! in_array($order->fulfilment_status, [EcommerceOrder::UNFULFILLED, EcommerceOrder::NOT_REQUIRED], true)) {
                $this->refuse("Order {$order->number} is being fulfilled: it can no longer be put on hold.");
            }

            $this->orders->update($order, ['status' => EcommerceOrder::ON_HOLD, 'held_status' => $order->status, 'status_reason' => $reason]);

            // Logged here, by what happened, so LogApiWriteActivity does not add its own vaguer entry.
            ActivityLogService::record('updated', 'Put '.$this->orderService->describe($order)." on hold: {$reason}", $order, ['source' => 'api', 'status' => EcommerceOrder::ON_HOLD]);
        });

        return $this->orderService->page($workspace, $id);
    }

    public function release(Workspace $workspace, int $id, string $reason): EcommerceOrder
    {
        $found = $this->orderService->find($workspace, $id);

        DB::transaction(function () use ($found, $reason) {
            $order = $this->orders->forConfirming($found, true);

            if ($order->status !== EcommerceOrder::ON_HOLD) {
                $this->refuse("Order {$order->number} is not on hold.");
            }

            $back = $order->held_status ?: EcommerceOrder::PENDING;

            $this->orders->update($order, ['status' => $back, 'held_status' => null, 'status_reason' => null]);

            ActivityLogService::record('updated', 'Released '.$this->orderService->describe($order)." from hold: {$reason}", $order, ['source' => 'api', 'status' => $back]);
        });

        return $this->orderService->page($workspace, $id);
    }

    /** Inside the transaction that places $order: held, by izSuite, when Hold Orders for Review is on and something wants a look. */
    public function holdAsPlaced(Workspace $workspace, EcommerceOrder $order, EcommerceSetting $settings): void
    {
        $notes = $settings->hold_for_review ? $this->review->notes($workspace, $order) : [];

        if ($notes === []) {
            return;
        }

        $reason = mb_substr('Held for review as it was placed: '.implode(' ', $notes), 0, 500);

        $this->orders->update($order, ['status' => EcommerceOrder::ON_HOLD, 'held_status' => EcommerceOrder::PENDING, 'status_reason' => $reason]);

        ActivityLogService::record('updated', 'Put '.$this->orderService->describe($order).' on hold: '.$reason, $order, ['source' => 'storefront', 'status' => EcommerceOrder::ON_HOLD]);
    }
}
