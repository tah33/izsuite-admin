<?php

namespace App\Services\Billing\Payments;

/**
 * What a finished checkout on the provider's page did (Phase D2): for an
 * invoice ($invoiceId set), the payment - its reference, amount in the
 * currency's smallest unit, and currency - or else a card saved; in both,
 * the card and the customer it is saved on.
 *
 * @property-read array{id: string, brand: ?string, last4: ?string, exp_month: ?int, exp_year: ?int}|null $card
 */
final class CheckoutOutcome
{
    public function __construct(
        public readonly int $workspaceId,
        public readonly ?int $invoiceId,
        public readonly string $customerId,
        public readonly bool $livemode,
        public readonly ?array $card,
        public readonly ?string $paymentRef = null,
        public readonly ?int $amountMinor = null,
        public readonly ?string $currency = null,
    ) {}

    public function isPayment(): bool
    {
        return $this->invoiceId !== null;
    }
}
