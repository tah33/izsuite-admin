<?php

namespace App\Services\Admin;

use App\Models\Billing\SubscriptionPayment;
use App\Models\User\User;
use App\Repositories\Admin\PaymentAdminRepository;
use App\Services\Billing\SubscriptionRefunds;
use App\Services\Shared\ActivityLogService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;

/**
 * Admin > Payments and Admin > Refunds (Doc 15 section 14 - Phase C2a), and
 * refunding a payment: a critical action (section 15.25), so it goes ahead
 * only once the admin has given the password again and a reason, which the
 * audit log keeps. The business is told once the refund is done.
 */
class PaymentAdminService
{
    public function __construct(
        protected PaymentAdminRepository $payments,
        protected SubscriptionRefunds $refunds,
        protected CriticalActionService $critical,
    ) {}

    public function page(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        return $this->payments->page($filters, $perPage);
    }

    public function refundsPage(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        return $this->payments->refundsPage($filters, $perPage);
    }

    /** Refunds $amount of payment $id for $reason - a transfer's with the bank's $transferReference. Answers what happened. */
    public function refund(int $id, User $admin, string $amount, string $reason, ?string $password, ?string $transferReference): string
    {
        $payment = $this->payments->find($id) ?? abort(404);
        $amount  = bcadd($amount, '0', 2);
        $what    = "{$payment->currency} {$amount} of the payment by ".($payment->isTransfer() ? 'bank transfer' : $payment->method()).($payment->invoice ? " for invoice {$payment->invoice->number}" : '');
        $context = ['workspace_id' => $payment->workspace_id, 'payment' => $payment->reference];

        // What cannot be refunded is refused before the password is asked for.
        $this->refunds->check($payment, $amount);
        $this->critical->confirm($admin, $password, $reason, "Refund {$what}");

        try {
            $refund = $this->refunds->refund($payment, $amount, trim($reason), $admin, $transferReference);
        } catch (ValidationException $e) {
            ActivityLogService::record('refused', "Could not refund {$what} to business \"{$payment->workspace->name}\": ".$e->getMessage(), $payment, $context, $admin);

            throw $e;
        }

        ActivityLogService::record('refunded', ($refund->succeeded() ? 'Refunded ' : 'Asked the card provider to refund ')."{$what} to business \"{$payment->workspace->name}\"", $refund, $context, $admin);

        return $this->said($payment, $refund->succeeded(), "{$refund->currency} {$refund->amount}");
    }

    private function said(SubscriptionPayment $payment, bool $done, string $amount): string
    {
        return match (true) {
            $done && $payment->isTransfer() => "The refund of {$amount} by bank transfer is recorded, and the business is told.",
            $done                           => "{$amount} is refunded to {$payment->method()}, and the business is told.",
            default                         => "The card provider is refunding {$amount}. It counts once the provider confirms it, and the business is told then.",
        };
    }
}
