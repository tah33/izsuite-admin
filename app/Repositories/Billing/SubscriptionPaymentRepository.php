<?php

namespace App\Repositories\Billing;

use App\Models\Billing\SubscriptionInvoice;
use App\Models\Billing\SubscriptionPayment;

class SubscriptionPaymentRepository
{
    public function find(string $provider, string $reference): ?SubscriptionPayment
    {
        return SubscriptionPayment::where('provider', $provider)->where('reference', $reference)->first();
    }

    /** Locked to the end of the transaction, while a refund of it is held (Doc 15 Phase C2a). */
    public function lock(int $id): ?SubscriptionPayment
    {
        return SubscriptionPayment::whereKey($id)->lockForUpdate()->first();
    }

    public function create(array $data): SubscriptionPayment
    {
        return SubscriptionPayment::create($data);
    }

    public function update(SubscriptionPayment $payment, array $data): SubscriptionPayment
    {
        $payment->update($data);

        return $payment;
    }

    /** Whether $invoice's card was tried on business day $day already. */
    public function attemptedOn(SubscriptionInvoice $invoice, string $day): bool
    {
        return SubscriptionPayment::where('subscription_invoice_id', $invoice->id)->whereDate('attempted_on', $day)->exists();
    }

    /** Whether a charge for $invoice is still processing - none other is tried meanwhile. */
    public function pendingFor(SubscriptionInvoice $invoice): bool
    {
        return SubscriptionPayment::where('subscription_invoice_id', $invoice->id)->where('status', SubscriptionPayment::PROCESSING)->exists();
    }

    /** $invoice's latest card payment or attempt. */
    public function latestFor(SubscriptionInvoice $invoice): ?SubscriptionPayment
    {
        return SubscriptionPayment::where('subscription_invoice_id', $invoice->id)->latest('id')->first();
    }
}
