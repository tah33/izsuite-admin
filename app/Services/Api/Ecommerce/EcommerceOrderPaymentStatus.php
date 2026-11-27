<?php

namespace App\Services\Api\Ecommerce;

use App\Models\User\EcommerceOrder;
use App\Models\User\SalesDocument;
use App\Repositories\User\EcommerceOrderRepository;
use App\Repositories\User\EcommercePaymentLinkRepository;

/**
 * An online order's payment status following its invoice (Doc 19 section
 * 19.22 - Phase F3), each time the invoice is settled again
 * (InvoiceSettlement) - whether its payment was recorded on the order's page
 * or in Sales, or reversed in Sales: Partially Paid, Paid or Refunded as the
 * invoice is. Owing all of it again - its payments reversed - it is Unpaid;
 * while nothing was paid, a payment waiting to be confirmed (Pending) or
 * marked failed stays so. Paid in full, its payment links still active are
 * Paid too (Phase F6a): they have nothing left to ask. Its status follows
 * as well (Phase G1, EcommerceOrderProgress): delivered and now paid, it is
 * Completed; completed and owing again, Processing.
 */
class EcommerceOrderPaymentStatus
{
    private const SETTLED = [EcommerceOrder::PARTIALLY_PAID, EcommerceOrder::PAID, 'partially_refunded', 'refunded'];

    public function __construct(
        protected EcommerceOrderRepository $orders,
        protected EcommercePaymentLinkRepository $links,
        protected EcommerceOrderProgress $progress,
    ) {}

    public function follow(SalesDocument $invoice): void
    {
        $order = $this->orders->ofInvoice($invoice);

        if (! $order) {
            return;
        }

        $status = match ($invoice->payment_status) {
            EcommerceOrder::PARTIALLY_PAID, EcommerceOrder::PAID, 'refunded' => $invoice->payment_status,
            SalesDocument::PAYMENT_UNPAID => in_array($order->payment_status, self::SETTLED, true) ? EcommerceOrder::UNPAID : $order->payment_status,
            default => $order->payment_status,
        };

        if ($status !== $order->payment_status) {
            $this->orders->update($order, ['payment_status' => $status]);
        }

        // Phase F6a: paid in full, however it came in, its links still active have nothing left to ask.
        if ($status === EcommerceOrder::PAID) {
            $this->links->markActivePaid($order);
        }

        // Phase G1: delivered and paid, it is completed - or no longer, owing again.
        $this->progress->follow($order);
    }
}
