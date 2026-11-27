<?php

namespace App\Services\Api\Ecommerce;

use App\Models\User\EcommerceOrder;
use App\Models\User\EcommerceOrderEmail;
use App\Models\User\EcommercePaymentLink;
use App\Models\User\PaymentAccount;
use App\Models\User\SalesPayment;
use App\Models\User\User;
use App\Models\User\Workspace;
use App\Repositories\User\EcommerceOrderRepository;
use App\Repositories\User\EcommercePaymentLinkRepository;
use App\Repositories\User\PaymentAccountRepository;
use App\Services\Api\Sales\RefusesConflicts;
use App\Services\Api\Sales\SalesPaymentService;
use App\Services\Documents\DocumentLinks;
use App\Services\Shared\ActivityLogService;
use App\Services\Shared\BusinessCalendar;
use App\Services\Shared\WorkspaceAccess;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * An online order's payment, received or failed (Doc 19 sections 19.20 and
 * 19.22 - Phase F3), under Confirm Orders:
 *
 * - review(): what recording a payment would do - what the order owes, the
 *   account its payment method takes money into, the customer's reference,
 *   the days it may be dated, and while it is pending what confirming it
 *   first would do (EcommerceOrderConfirmation::review());
 * - record(): the customer's payment - its amount (up to what is owed), day
 *   and reference - recorded as Sales records one (SalesPaymentService): a
 *   customer payment of the order's invoice, numbered as receipts are, into
 *   that account and its history. A pending order is confirmed first, its
 *   invoice issued, in the same transaction. Its payment status then follows
 *   the invoice (EcommerceOrderPaymentStatus): Partially Paid, then Paid;
 * - fail(): the payment marked failed, with why: the order still owed, its
 *   failures counted (Phase F4's review points out two or more);
 * - either way the customer e-mailed - "Payment received" or "not received",
 *   never the business's why - and the order's timeline told.
 *
 * Phase F6b: while the order's payment link is reported - its customer said
 * on its page that they paid, by which of its methods and with what
 * reference (StorefrontPaymentLinkReport) - the payment starts from that
 * report: what the link asks (never more than is owed), their reference, and
 * the account of the method they paid by. Recorded, the link is Paid; marked
 * not received, its report is cleared, so the link takes another while it
 * works - a part-paid order's report too.
 *
 * A recorded payment is never changed here: it is reversed in Sales, and
 * refunded through Phase I.
 */
class EcommerceOrderPayments
{
    use RefusesConflicts;

    /** A recorded payment's method, by the order's payment method's type. */
    private const METHODS = ['bank_transfer' => 'bank_transfer', 'mobile_money' => 'mobile_money', 'cash_on_delivery' => 'cash', 'pay_on_pickup' => 'cash'];

    /** The payment statuses whose order still waits for its money - and can have it marked failed. */
    private const AWAITED = [EcommerceOrder::UNPAID, EcommerceOrder::PAYMENT_PENDING, EcommerceOrder::PAYMENT_FAILED];

    public function __construct(
        protected EcommerceOrderService $orderService,
        protected EcommerceOrderRepository $orders,
        protected EcommerceOrderConfirmation $confirmation,
        protected SalesPaymentService $salesPayments,
        protected PaymentAccountRepository $accounts,
        protected DocumentLinks $links,
        protected StoreOrderMailer $mailer,
        protected BusinessCalendar $calendar,
        protected WorkspaceAccess $access,
        protected EcommercePaymentLinkRepository $paymentLinks,
    ) {}

    /**
     * What recording a payment of order $id would do today - from its
     * payment link's report while it has one (Phase F6b).
     *
     * @return array{order: EcommerceOrder, problems: list<string>, balance: string, amount: string, account: ?PaymentAccount, method: ?string, reference: ?string, report: ?EcommercePaymentLink, today: string, from: string, confirmation: ?array}
     */
    public function review(Workspace $workspace, int $id): array
    {
        $order   = $this->orderService->find($workspace, $id);
        $today   = $this->calendar->today($workspace)->toDateString();
        $report  = $this->paymentLinks->reportedFor($order, false);
        $account = $this->accountOf($workspace, $order, $report);

        $this->checkPayable($order);

        // Pending, it is confirmed first: its invoice, dated today, is what the payment pays.
        $confirmation = $order->status === EcommerceOrder::PENDING ? $this->confirmation->review($workspace, $id) : null;
        $owed         = $this->owed($order);

        return [
            'order'        => $order,
            'problems'     => [...($account ? [] : [$this->noAccount($order, $report)]), ...($confirmation['problems'] ?? [])],
            'balance'      => $owed,
            'amount'       => $report ? $report->askedOf($owed) : $owed,
            'account'      => $account,
            'method'       => $report ? ($report->reported_method['name'] ?? null) : ($order->payment_snapshot['name'] ?? null),
            'reference'    => $report ? $report->reported_reference : $order->payment_reference,
            'report'       => $report,
            'today'        => $today,
            'from'         => $order->invoice ? substr((string) $order->invoice->document_date, 0, 10) : $today,
            'confirmation' => $confirmation,
        ];
    }

    /**
     * $data as RecordEcommercePaymentRequest takes it: amount, payment_date
     * and reference. The order, as its page shows it, and the payment.
     *
     * @return array{order: EcommerceOrder, payment: SalesPayment}
     */
    public function record(Workspace $workspace, User $user, int $id, array $data): array
    {
        $found = $this->orderService->find($workspace, $id);
        $today = $this->calendar->today($workspace)->toDateString();

        $payment = DB::transaction(function () use ($workspace, $user, $found, $today, $data): SalesPayment {
            $order = $this->orders->forConfirming($found, true);

            $this->checkPayable($order);

            $report  = $this->paymentLinks->reportedFor($order, true);
            $account = $this->accountOf($workspace, $order, $report) ?? $this->refuse($this->noAccount($order, $report));
            $owed    = $this->owed($order);

            if (bccomp((string) $data['amount'], $owed, 2) > 0) {
                throw ValidationException::withMessages(['amount' => "Order {$order->number} owes {$order->currency} {$owed}: record that much at most."]);
            }

            $type    = $report ? ($report->reported_method['type'] ?? '') : ($order->payment_snapshot['type'] ?? '');
            $invoice = $order->status === EcommerceOrder::PENDING ? $this->confirmation->confirmLocked($workspace, $user, $order, $today) : $order->invoice;
            $payment = $this->salesPayments->record($workspace, $invoice->id, $user, [
                'payment_account_id' => $account->id,
                'payment_date'       => $data['payment_date'],
                'amount'             => $data['amount'],
                'method'             => self::METHODS[$type] ?? 'other',
                'reference'          => trim((string) ($data['reference'] ?? '')) ?: null,
            ]);

            // Phase F6b: the report is the payment recorded - never paid again.
            if ($report) {
                $this->paymentLinks->update($report, ['status' => EcommercePaymentLink::PAID]);
            }

            $this->mailer->queue($order, EcommerceOrderEmail::PAYMENT_RECEIVED, $this->links->url($this->links->ensure($workspace, $invoice, $user)), [
                'amount' => (string) $payment->applied_amount, 'reference' => $payment->reference, 'balance' => (string) $payment->balance_after,
            ]);

            ActivityLogService::record('updated', "Recorded payment {$payment->number} of {$account->currency} {$payment->amount} for ".$this->orderService->describe($order)
                .($report ? ', reported through its payment link' : ''), $order, ['source' => 'api']);

            return $payment;
        });

        return ['order' => $this->orderService->page($workspace, $id), 'payment' => $payment];
    }

    /** Order $id's payment marked failed by $user, with $reason - its payment link's report cleared (Phase F6b). The order, as its page shows it. */
    public function fail(Workspace $workspace, User $user, int $id, string $reason): EcommerceOrder
    {
        $found = $this->orderService->find($workspace, $id);

        DB::transaction(function () use ($workspace, $user, $found, $reason) {
            $order  = $this->orders->forConfirming($found, true);
            $report = $this->paymentLinks->reportedFor($order, true);
            $waited = in_array($order->payment_status, self::AWAITED, true);

            $this->checkOpen($order);

            // A part-paid order's report may be marked not received too: its own status stays.
            if (! $waited && ! $report) {
                $this->refuse($order->payment_status === EcommerceOrder::PAID
                    ? "Order {$order->number} is paid already."
                    : "Part of order {$order->number} is paid: record the rest, or reverse its payment in Sales first.");
            }

            $this->orders->update($order, [
                ...($waited ? ['payment_status' => EcommerceOrder::PAYMENT_FAILED] : []),
                'payment_failures'       => $order->payment_failures + 1,
                'payment_failure_reason' => $reason,
            ]);

            // The link takes another report while it works.
            if ($report) {
                $this->paymentLinks->update($report, ['reported_at' => null, 'reported_method' => null, 'reported_reference' => null]);
            }

            // Its invoice's link, once it has one; before, the customer has the order's own page from their first e-mail.
            $this->mailer->queue($order, EcommerceOrderEmail::PAYMENT_FAILED, $order->invoice ? $this->links->url($this->links->ensure($workspace, $order->invoice, $user)) : null, [
                'balance' => $this->owed($order),
            ]);

            ActivityLogService::record('updated', 'Marked the payment '.($report ? 'reported through the payment link ' : '').'of '.$this->orderService->describe($order)." as failed: {$reason}", $order, [
                'source' => 'api', 'payment_status' => $waited ? EcommerceOrder::PAYMENT_FAILED : $order->payment_status,
            ]);
        });

        return $this->orderService->page($workspace, $id);
    }

    /** An order that takes a payment: open, and owing something - a payment link's too (Phase F6a). */
    public function checkPayable(EcommerceOrder $order): void
    {
        $this->checkOpen($order);

        if (! in_array($order->payment_status, [...self::AWAITED, EcommerceOrder::PARTIALLY_PAID], true) || bccomp($this->owed($order), '0', 2) <= 0) {
            $this->refuse("Order {$order->number} is paid already.");
        }
    }

    /** Neither cancelled nor on hold (Phase F4). */
    private function checkOpen(EcommerceOrder $order): void
    {
        match ($order->status) {
            'cancelled' => $this->refuse("Order {$order->number} is cancelled: it takes no payment."),
            'on_hold'   => $this->refuse("Order {$order->number} is on hold: release it first."),
            default     => null,
        };
    }

    /** What the order owes: its invoice's balance - or, before it has one, its total. */
    public function owed(EcommerceOrder $order): string
    {
        return (string) ($order->invoice ? $order->invoice->balance_due : $order->grand_total);
    }

    /**
     * The account the order's payment method took money into as it was placed
     * (its payment snapshot's) - or, with its payment link reported (Phase
     * F6b), the account of the method its customer paid by, as it was then -
     * while it is active, takes customer payments and may be used at the
     * user's locations.
     */
    private function accountOf(Workspace $workspace, EcommerceOrder $order, ?EcommercePaymentLink $report): ?PaymentAccount
    {
        $id      = $report ? ($report->reported_method['payment_account_id'] ?? null) : ($order->payment_snapshot['payment_account_id'] ?? null);
        $account = $id ? $this->accounts->findFor($workspace, (int) $id, $this->access->locations()) : null;

        return $account && $account->is_active && $account->allow_customer_payments ? $account : null;
    }

    private function noAccount(EcommerceOrder $order, ?EcommercePaymentLink $report): string
    {
        $name = $report ? ($report->reported_method['name'] ?? 'the way they paid') : ($order->payment_snapshot['name'] ?? 'its payment method');

        return "The account {$name} takes money into is no longer active for customer payments here: record this payment in Sales, into an account of your choice.";
    }
}
