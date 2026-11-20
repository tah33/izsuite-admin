<?php

namespace App\Services\Api\Pos;

use App\Models\User\PosOrderPayment;
use App\Models\User\Workspace;
use App\Repositories\User\PosOrderRepository;
use App\Repositories\User\PosSessionRepository;
use App\Services\Api\Sales\InvoiceSettlement;
use App\Services\Api\Sales\RefusesConflicts;
use App\Services\Api\Sales\SalesDocumentService;
use App\Services\Shared\ActivityLogService;
use App\Services\Shared\WorkspaceAccess;
use App\Services\User\Apps\WorkspaceApps;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Pending POS payments (Doc 18 section 18.16 - Phase D2): a sale's payment
 * by a method that waits to be confirmed - a card or mobile payment the
 * cashier cannot see arrive. It is the order's, but no customer payment
 * yet: nothing is in its account, and the invoice still owes it.
 *
 * Whoever may confirm pending payments sees a session's - one at a location
 * they may use - and confirms each once: it becomes the customer payment,
 * dated the day it is confirmed, into the account its method's money was to
 * go into at the sale, and pays the invoice. Or declines it, saying why: the
 * invoice still owes it, paid from the invoice. Each is logged on the
 * invoice.
 */
class PosPendingPayments
{
    use RefusesConflicts;

    public function __construct(
        protected PosOrderRepository $orders,
        protected PosSessionRepository $sessions,
        protected PosSale $sale,
        protected InvoiceSettlement $settlement,
        protected SalesDocumentService $salesDocuments,
        protected WorkspaceAccess $access,
    ) {}

    /** The session's payments waiting to be confirmed, in the order they were taken. */
    public function of(Workspace $workspace, int $sessionId): Collection
    {
        $session = $this->sessions->findFor($workspace, $sessionId, $this->access->locations()) ?? abort(404, 'There is no such session.');

        return $this->orders->pendingPaymentsIn($session);
    }

    public function confirm(Workspace $workspace, int $id): PosOrderPayment
    {
        $payment = $this->find($workspace, $id);
        $order   = $payment->order;
        $invoice = $order->invoice;
        $user    = $this->access->user();
        $today   = $this->salesDocuments->today($workspace);

        DB::transaction(function () use ($workspace, $payment, $order, $invoice, $user, $today): void {
            $this->stillPending($this->orders->lockPayment($payment));

            if ($payment->payment_account_id === null) {
                $this->refuse("The account this {$payment->method_name} payment was to go into is gone: decline it, and take the payment again from the invoice.");
            }

            $received = $this->sale->receive($workspace, $user, $invoice, $order->location_id, $payment->payment_account_id, [
                'method' => $payment->method_type, 'reference' => $payment->reference, 'amount' => (string) $payment->amount,
            ], bcsub((string) $invoice->refresh()->balance_due, (string) $payment->amount, 2), $order->currency, (string) $order->exchange_rate, $today, WorkspaceApps::for($workspace)->row('pos')?->app_id);

            $this->orders->updatePayment($payment, ['status' => PosOrderPayment::COMPLETED, 'sales_payment_id' => $received->id, 'closed_by' => $user->id, 'closed_at' => now()]);

            $this->settlement->settle($invoice);
        });

        ActivityLogService::record('updated', "Confirmed the {$payment->method_name} payment of {$order->currency} {$payment->amount} for {$order->number}", $invoice, ['source' => 'api']);

        return $payment;
    }

    public function decline(Workspace $workspace, int $id, string $reason): PosOrderPayment
    {
        $payment = $this->find($workspace, $id);
        $order   = $payment->order;

        DB::transaction(function () use ($payment, $reason): void {
            $this->stillPending($this->orders->lockPayment($payment));

            $this->orders->updatePayment($payment, ['status' => PosOrderPayment::DECLINED, 'decline_reason' => $reason, 'closed_by' => $this->access->user()->id, 'closed_at' => now()]);
        });

        ActivityLogService::record('updated', "Declined the {$payment->method_name} payment of {$order->currency} {$payment->amount} for {$order->number}: {$reason}", $order->invoice, ['source' => 'api']);

        return $payment;
    }

    /** One of the business's order payments, at a location the user may use - else as if there were none. */
    private function find(Workspace $workspace, int $id): PosOrderPayment
    {
        return $this->orders->findPayment($workspace, $id, $this->access->locations()) ?? abort(404, 'There is no such payment.');
    }

    private function stillPending(PosOrderPayment $payment): void
    {
        if (! $payment->isPending()) {
            $this->refuse($payment->status === PosOrderPayment::DECLINED
                ? "This {$payment->method_name} payment was declined."
                : "This {$payment->method_name} payment was confirmed already.");
        }
    }
}
