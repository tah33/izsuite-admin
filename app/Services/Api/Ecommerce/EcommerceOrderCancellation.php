<?php

namespace App\Services\Api\Ecommerce;

use App\Enums\DocumentType;
use App\Enums\SalesDocumentStatus;
use App\Models\User\EcommerceOrder;
use App\Models\User\EcommerceOrderEmail;
use App\Models\User\EcommercePaymentLink;
use App\Models\User\User;
use App\Models\User\Workspace;
use App\Repositories\User\CatalogProductRepository;
use App\Repositories\User\EcommerceFulfilmentRepository;
use App\Repositories\User\EcommerceOrderRepository;
use App\Repositories\User\EcommercePaymentLinkRepository;
use App\Services\Api\Sales\RefusesConflicts;
use App\Services\Api\Sales\SalesDocumentFlow;
use App\Services\Shared\ActivityLogService;
use Illuminate\Support\Facades\DB;

/**
 * Cancelling an online order (Doc 19 section 19.21 - Phase F4), under Cancel
 * Orders, with a reason in its timeline: a pending, confirmed or held order
 * not yet shipped or collected - one on its way or with its customer is
 * returned instead (Phase I). All in one transaction, the order locked:
 *
 * - an invoice with payments - or anything else settling it - is never
 *   refunded by cancelling (19.21): the order is refused until they are
 *   refunded through a credit note (Phase I) or reversed in Sales;
 * - confirmed once, its Limited quantities are given back, to the listings
 *   and variants still Limited, and its invoice is voided as Sales voids one
 *   (its secure links stop working with it);
 * - the order is Cancelled, and its customer e-mailed that it is - never why;
 * - its payment links still active are revoked (Phase F6a);
 * - from Phase G1 a processing order is cancelled too while it is only ready
 *   for pickup: its readiness is cancelled with it.
 */
class EcommerceOrderCancellation
{
    use RefusesConflicts;

    /** Phase G1: a processing order too, while it is only ready for pickup (NOT_SENT). */
    private const CANCELLABLE = [EcommerceOrder::PENDING, EcommerceOrder::CONFIRMED, EcommerceOrder::ON_HOLD, EcommerceOrder::PROCESSING];

    /** Fulfilment statuses of an order not yet shipped or collected. */
    private const NOT_SENT = [EcommerceOrder::UNFULFILLED, EcommerceOrder::NOT_REQUIRED, 'ready_for_pickup'];

    public function __construct(
        protected EcommerceOrderService $orderService,
        protected EcommerceOrderRepository $orders,
        protected EcommerceOrderChecks $checks,
        protected CatalogProductRepository $catalog,
        protected SalesDocumentFlow $salesFlow,
        protected StoreOrderMailer $mailer,
        protected EcommercePaymentLinkRepository $paymentLinks,
        protected EcommerceFulfilmentRepository $fulfilments,
    ) {}

    public function cancel(Workspace $workspace, User $user, int $id, string $reason): EcommerceOrder
    {
        $found = $this->orderService->find($workspace, $id);

        DB::transaction(function () use ($workspace, $user, $found, $reason) {
            $order   = $this->orders->forConfirming($found, true);
            $invoice = $order->invoice;

            if (! in_array($order->status, self::CANCELLABLE, true)) {
                $this->refuse($order->status === EcommerceOrder::CANCELLED
                    ? "Order {$order->number} is cancelled already."
                    : "Only an order not yet completed is cancelled: {$order->number} is ".str_replace('_', ' ', $order->status).'.');
            }

            if (! in_array($order->fulfilment_status, self::NOT_SENT, true)) {
                $this->refuse("Order {$order->number} is on its way or with its customer: it is returned, not cancelled.");
            }

            if ($invoice && $invoice->status === SalesDocumentStatus::Issued && $invoice->isSettledAtAll()) {
                $this->refuse("Payments settle part of order {$order->number}'s invoice, {$invoice->number}: refund them through a credit note, or reverse them in Sales, then cancel the order.");
            }

            // Counted down as it was confirmed: given back.
            if ($order->confirmed_at !== null) {
                foreach ($this->checks->taken($workspace, $order, true) as $taken) {
                    $this->catalog->setLeft($taken['stock'], bcadd((string) ($taken['stock']->stock_quantity ?? '0'), $taken['quantity'], 3));
                }
            }

            if ($invoice && $invoice->status === SalesDocumentStatus::Issued) {
                $this->salesFlow->void($workspace, DocumentType::Invoice, $invoice->id, $user, "Online order {$order->number} cancelled: {$reason}");
            }

            // Phase F6a: its payment links still active ask no more.
            $this->paymentLinks->revokeActive($order, $user->id, EcommercePaymentLink::CANCELLED);

            // Phase G1: ready for pickup, it is not collected now.
            $this->fulfilments->cancelReady($order, "The order was cancelled: {$reason}");

            $this->orders->update($order, [
                'status'        => EcommerceOrder::CANCELLED,
                'held_status'   => null,
                'status_reason' => $reason,
                // Phase G1: its readiness for pickup gone with it.
                ...($order->fulfilment_status === EcommerceOrder::READY_FOR_PICKUP ? ['fulfilment_status' => EcommerceOrder::UNFULFILLED] : []),
            ]);

            $this->mailer->queue($order, EcommerceOrderEmail::ORDER_CANCELLED, null);

            ActivityLogService::record('updated', 'Cancelled '.$this->orderService->describe($order).": {$reason}", $order, ['source' => 'api', 'status' => EcommerceOrder::CANCELLED]);
        });

        return $this->orderService->page($workspace, $id);
    }
}
