<?php

namespace App\Services\Billing\Payments;

use App\Models\Billing\SubscriptionInvoice;
use App\Models\Billing\SubscriptionPayment;
use App\Models\Billing\WorkspaceCard;
use App\Models\User\Workspace;

/**
 * A card payment provider (Doc 13 section 15, Doc 15 section 15 - Phase D2):
 * its own page to pay an invoice or save a card - card details never reach
 * izSuite - what a finished checkout did, its verified notifications, and
 * removing a saved card. Stripe is the one built; others can follow.
 * Failing to reach it, or refused, it throws CardPaymentError.
 */
interface CardGateway
{
    /** Its name in records: "stripe". */
    public function name(): string;

    /** Whether cards can be taken: switched on, with the keys of its mode. */
    public function available(): bool;

    /** Whether payments go to its live mode (else test). */
    public function live(): bool;

    /** A new customer at the provider for $workspace, billed as $name at $email: its id. */
    public function customer(Workspace $workspace, string $name, ?string $email): string;

    /**
     * The provider's page to pay $invoice by card, saving the card for later
     * payments: its address. Finished, it sends the business back to
     * $returnUrl with `card_checkout` naming the checkout; given up, without.
     */
    public function checkout(SubscriptionInvoice $invoice, string $customerId, string $returnUrl): string;

    /** The provider's page to save a card for later payments: its address, coming back as checkout() does. */
    public function cardSetup(Workspace $workspace, string $customerId, string $returnUrl): string;

    /** What checkout $sessionId did, once finished - null before. */
    public function finished(string $sessionId): ?CheckoutOutcome;

    /**
     * Charges $card, saved on its customer, for $invoice without the business
     * there (Phase D3). $key makes a repeat of the same attempt the same
     * charge. The bank refusing is an answer, not an error.
     */
    public function charge(SubscriptionInvoice $invoice, WorkspaceCard $card, string $key): ChargeResult;

    /**
     * Gives back $amount of $payment, a payment taken by the provider (Doc 15
     * Phase C2a). $key makes a repeat the same refund; $metadata travels with
     * it, for its notifications. The provider refusing it throws.
     */
    public function refund(SubscriptionPayment $payment, string $amount, string $key, array $metadata): RefundOutcome;

    /**
     * What a notification reports: a finished checkout, a payment for an
     * invoice that succeeded, or a refund that moved on (Doc 15 Phase C2a) -
     * null for one to ignore. Throws InvalidNotification when it is not the
     * provider's.
     */
    public function notified(string $payload, ?string $signature): CheckoutOutcome|RefundOutcome|null;

    /** Takes a saved card off its customer at the provider. */
    public function removeCard(string $cardId): void;
}
