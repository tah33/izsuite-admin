<?php

namespace App\Services\Billing;

use App\Models\Billing\SubscriptionPayment;
use App\Models\Billing\SubscriptionRefund;
use App\Models\User\User;
use App\Repositories\Billing\SubscriptionPaymentRepository;
use App\Repositories\Billing\SubscriptionRefundRepository;
use App\Services\Billing\Payments\CardGateway;
use App\Services\Billing\Payments\CardPaymentError;
use App\Services\Billing\Payments\RefundOutcome;
use App\Support\MinorUnits;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Giving back money paid for izSuite's subscription (Doc 15 section 14 -
 * Phase C2a). A card payment's refund goes through the card provider and
 * counts once the provider confirms it, at once or by its notification; a
 * bank transfer's is recorded by izSuite once it has sent the money back.
 * Never more than was paid, counting refunds still waiting. A refund made in
 * the provider's own dashboard is kept too, for a payment izSuite knows.
 *
 * A refund changes neither the invoice nor the plan. The business hears of
 * each one once it is done (SubscriptionBilling::refunded).
 */
class SubscriptionRefunds
{
    public function __construct(
        private CardGateway $gateway,
        private SubscriptionRefundRepository $refunds,
        private SubscriptionPaymentRepository $payments,
        private SubscriptionBilling $billing,
    ) {}

    /** What is left to give back of $payment: its amount, less refunds done or waiting - nothing of one that did not go through. */
    public function refundable(SubscriptionPayment $payment): string
    {
        $left = bcsub((string) $payment->amount, $this->refunds->heldFor($payment), 2);

        return $payment->succeeded() && bccomp($left, '0', 2) > 0 ? $left : '0.00';
    }

    /** Refuses $amount of $payment, on the amount field, unless it can be given back now. */
    public function check(SubscriptionPayment $payment, string $amount): void
    {
        $left = $this->refundable($payment);
        $why  = match (true) {
            ! $payment->succeeded()                       => 'Only a payment that went through can be refunded.',
            bccomp($left, '0', 2) <= 0                     => 'This payment is refunded in full already.',
            bccomp($amount, '0', 2) <= 0                   => 'Enter the amount to refund.',
            bccomp($amount, $left, 2) > 0                  => "No more than {$payment->currency} {$left} is left to refund.",
            ! $payment->isTransfer() && ($payment->provider !== $this->gateway->name() || ! $this->gateway->available())
                                                           => 'Card payments are not set up, so the card provider cannot refund it now.',
            default                                        => null,
        };

        if ($why) {
            throw ValidationException::withMessages(['amount' => $why]);
        }
    }

    /**
     * Gives back $amount of $payment for $reason, as $admin asks: through the
     * card provider, or - a transfer - recorded as sent, with the bank's
     * $transferReference. Answers the refund as it then stands; the provider
     * refusing it is kept, and answered on the amount field.
     */
    public function refund(SubscriptionPayment $payment, string $amount, string $reason, User $admin, ?string $transferReference = null): SubscriptionRefund
    {
        $amount = bcadd($amount, '0', 2);

        // Held against the payment before the provider is asked: two refunds at once never give back more than was paid.
        $refund = DB::transaction(function () use ($payment, $amount, $reason, $admin, $transferReference) {
            $locked = $this->payments->lock($payment->id) ?? $payment;

            $this->check($locked, $amount);

            return $this->refunds->create([
                'workspace_id'            => $locked->workspace_id,
                'subscription_payment_id' => $locked->id,
                'subscription_invoice_id' => $locked->subscription_invoice_id,
                'provider'                => $locked->provider,
                'transfer_reference'      => $locked->isTransfer() ? $transferReference : null,
                'currency'                => $locked->currency,
                'amount'                  => $amount,
                'status'                  => $locked->isTransfer() ? SubscriptionRefund::SUCCEEDED : SubscriptionRefund::PENDING,
                'reason'                  => $reason,
                'refunded_by'             => $admin->id,
                'confirmed_at'            => $locked->isTransfer() ? now() : null,
            ]);
        });

        if ($payment->isTransfer()) {
            $this->billing->refunded($refund);

            return $refund;
        }

        try {
            $outcome = $this->gateway->refund($payment, $amount, "izsuite-refund-{$refund->id}", array_filter([
                'refund_id' => $refund->id, 'workspace_id' => $payment->workspace_id, 'invoice_id' => $payment->subscription_invoice_id,
            ]));
        } catch (CardPaymentError $e) {
            report($e);
            $this->refunds->update($refund, ['status' => SubscriptionRefund::FAILED, 'note' => Str::limit($e->getMessage(), 250)]);

            throw ValidationException::withMessages(['amount' => $e->getMessage()]);
        }

        return $this->settle($outcome, $refund->id) ?? $refund;
    }

    /**
     * The provider's word on a refund - its answer to izSuite ($refundId), or
     * a notification - taken once: izSuite's refund moves on, and one made in
     * the provider's dashboard is kept when it gives back a payment izSuite
     * knows. The business hears of it when it is done. Null for one not ours.
     */
    public function settle(RefundOutcome $outcome, ?int $refundId = null): ?SubscriptionRefund
    {
        [$refund, $done] = DB::transaction(function () use ($outcome, $refundId) {
            $provider = $this->gateway->name();
            $refund   = ($outcome->reference ? $this->refunds->lockByReference($provider, $outcome->reference) : null)
                ?? (($id = $refundId ?? $outcome->refundId) ? $this->refunds->lock($id) : null)
                ?? $this->fromDashboard($outcome);

            if (! $refund) {
                return [null, false];
            }

            $was      = $refund->status;
            $status   = [RefundOutcome::SUCCEEDED => SubscriptionRefund::SUCCEEDED, RefundOutcome::FAILED => SubscriptionRefund::FAILED][$outcome->status] ?? SubscriptionRefund::PENDING;

            $this->refunds->update($refund, [
                'reference'    => $outcome->reference ?: $refund->reference,
                'status'       => $status,
                'note'         => $outcome->note,
                'confirmed_at' => $status === SubscriptionRefund::SUCCEEDED ? ($refund->confirmed_at ?? now()) : null,
            ]);

            return [$refund, $was !== SubscriptionRefund::SUCCEEDED && $status === SubscriptionRefund::SUCCEEDED];
        });

        if ($done) {
            $this->billing->refunded($refund);
        }

        return $refund;
    }

    /** A refund made in the provider's own dashboard, of a payment izSuite knows - kept, waiting for settle() to say where it stands. */
    private function fromDashboard(RefundOutcome $outcome): ?SubscriptionRefund
    {
        $payment = $outcome->paymentRef ? $this->payments->find($this->gateway->name(), $outcome->paymentRef) : null;

        if (! $payment || ! $outcome->reference) {
            return null;
        }

        return $this->refunds->create([
            'workspace_id'            => $payment->workspace_id,
            'subscription_payment_id' => $payment->id,
            'subscription_invoice_id' => $payment->subscription_invoice_id,
            'provider'                => $payment->provider,
            'reference'               => $outcome->reference,
            'currency'                => $outcome->currency ?: $payment->currency,
            'amount'                  => MinorUnits::toDecimal($outcome->amountMinor, $outcome->currency ?: $payment->currency),
            'status'                  => SubscriptionRefund::PENDING,
            'reason'                  => "Refunded in the card provider's own dashboard.",
        ]);
    }
}
