<?php

namespace App\Repositories\Billing;

use App\Models\Billing\SubscriptionPayment;
use App\Models\Billing\SubscriptionRefund;

/** Refunds of izSuite's subscription payments (Doc 15 Phase C2a). */
class SubscriptionRefundRepository
{
    public function create(array $data): SubscriptionRefund
    {
        return SubscriptionRefund::create($data);
    }

    public function update(SubscriptionRefund $refund, array $data): SubscriptionRefund
    {
        $refund->update($data);

        return $refund;
    }

    /** Locked to the end of the transaction: the provider's answer and its notification can arrive together. */
    public function lock(int $id): ?SubscriptionRefund
    {
        return SubscriptionRefund::whereKey($id)->lockForUpdate()->first();
    }

    public function lockByReference(string $provider, string $reference): ?SubscriptionRefund
    {
        return SubscriptionRefund::where('provider', $provider)->where('reference', $reference)->lockForUpdate()->first();
    }

    /** What of $payment is given back or on its way: its refunds done or still waiting. */
    public function heldFor(SubscriptionPayment $payment): string
    {
        return SubscriptionRefund::where('subscription_payment_id', $payment->id)
            ->whereIn('status', [SubscriptionRefund::PENDING, SubscriptionRefund::SUCCEEDED])
            ->pluck('amount')
            ->reduce(fn (string $sum, $amount) => bcadd($sum, (string) $amount, 2), '0.00');
    }
}
