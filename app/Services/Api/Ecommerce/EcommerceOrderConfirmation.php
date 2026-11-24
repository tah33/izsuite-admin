<?php

namespace App\Services\Api\Ecommerce;

use App\Models\User\EcommerceOrder;
use App\Models\User\EcommerceOrderEmail;
use App\Models\User\SalesDocument;
use App\Models\User\User;
use App\Models\User\Workspace;
use App\Repositories\User\CatalogProductRepository;
use App\Repositories\User\EcommerceOrderRepository;
use App\Services\Api\Sales\RefusesConflicts;
use App\Services\Documents\DocumentLinks;
use App\Services\Shared\ActivityLogService;
use App\Services\Shared\BusinessCalendar;
use Illuminate\Support\Facades\DB;

/**
 * Confirming an online order (Doc 19 section 19.20 - Phase F2), under Confirm
 * Orders: what confirming it would do, then doing it - all in one
 * transaction, the order locked so it is confirmed once:
 *
 * - its checks (EcommerceOrderChecks) passed again, under the locks;
 * - its invoice issued (EcommerceOrderInvoicing): from then it counts as a
 *   sale, once;
 * - its Limited quantities counted down - never a stock movement;
 * - the order Confirmed and linked to its invoice. Its payment status stays
 *   as it was: cash on delivery stays Unpaid until its payment is recorded
 *   (Phase F3);
 * - the invoice's secure link made, and the customer e-mailed "Your order is
 *   confirmed" with it, once all is saved (StoreOrderMailer);
 * - the order's confirmation on its timeline, and the invoice issued for it
 *   on the invoice's.
 */
class EcommerceOrderConfirmation
{
    use RefusesConflicts;

    public function __construct(
        protected EcommerceOrderService $orderService,
        protected EcommerceOrderRepository $orders,
        protected EcommerceOrderChecks $checks,
        protected EcommerceOrderInvoicing $invoicing,
        protected CatalogProductRepository $catalog,
        protected DocumentLinks $links,
        protected StoreOrderMailer $mailer,
        protected BusinessCalendar $calendar,
    ) {}

    /**
     * What confirming order $id would do today: its checks, the day its
     * invoice would be dated, and whether it can be confirmed.
     *
     * @return array{order: EcommerceOrder, date: string, ready: bool, problems: list<string>, notices: list<string>, prices: list<array>, stock: list<array>, contact: \App\Models\User\Contact}
     */
    public function review(Workspace $workspace, int $id): array
    {
        $order  = $this->orders->forConfirming($this->orderService->find($workspace, $id), false);
        $today  = $this->calendar->today($workspace)->toDateString();
        $checks = $this->checks->run($workspace, $order, $today);

        return [...$checks, 'order' => $order, 'date' => $today, 'ready' => $checks['problems'] === []];
    }

    /** Order $id confirmed by $user, its invoice issued - or refused with what stands in the way. The order, as its page shows it. */
    public function confirm(Workspace $workspace, User $user, int $id): EcommerceOrder
    {
        $found = $this->orderService->find($workspace, $id);
        $today = $this->calendar->today($workspace)->toDateString();

        DB::transaction(fn () => $this->confirmLocked($workspace, $user, $this->orders->forConfirming($found, true), $today));

        return $this->orderService->find($workspace, $id);
    }

    /**
     * $order - read again and locked (EcommerceOrderRepository::forConfirming),
     * inside the caller's transaction - confirmed on $today: its checks
     * passed, its invoice issued and returned. Phase F3 confirms a pending
     * order so before recording its payment.
     */
    public function confirmLocked(Workspace $workspace, User $user, EcommerceOrder $order, string $today): SalesDocument
    {
        $checks = $this->checks->run($workspace, $order, $today, true);

        if ($checks['problems'] !== []) {
            $this->refuse($checks['problems'][0]);
        }

        $invoice = $this->invoicing->issue($workspace, $user, $order, $checks['contact'], $today);

        foreach ($checks['stock'] as $stock) {
            $this->catalog->setLeft($stock['stock'], $stock['after']);
        }

        $this->orders->update($order, [
            'status'            => EcommerceOrder::CONFIRMED,
            'sales_document_id' => $invoice->id,
            'confirmed_at'      => now(),
            'confirmed_by'      => $user->id,
        ]);

        $this->mailer->queue($order, EcommerceOrderEmail::ORDER_CONFIRMED, $this->links->url($this->links->ensure($workspace, $invoice, $user)));

        // Logged here, by what happened, so LogApiWriteActivity does not add its own vaguer entry.
        ActivityLogService::record('updated', 'Confirmed '.$this->orderService->describe($order)." and issued invoice {$invoice->number}", $order, ['source' => 'api', 'status' => EcommerceOrder::CONFIRMED]);
        ActivityLogService::record('created', "Issued invoice {$invoice->number} for online order {$order->number}", $invoice, ['source' => 'api']);

        return $invoice;
    }
}
