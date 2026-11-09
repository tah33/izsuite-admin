<?php

namespace App\Services\Api\Billing;

use App\Models\Billing\SubscriptionPayment;
use App\Models\User\User;
use App\Models\User\Workspace;
use App\Repositories\Billing\SubscriptionInvoiceRepository;
use App\Services\Api\Sales\RefusesConflicts;
use App\Services\Billing\CardPayments;
use App\Services\Shared\BusinessCalendar;

/**
 * Paying izSuite by card from the Subscription page (Doc 13 sections 11 and
 * 15 - Phase D2): the invoice to pay on the card provider's page, coming
 * back from it, and the saved card - added or replaced on the provider's
 * page, or removed. Not offered while card payments are off in the admin.
 */
class SubscriptionCardService
{
    use RefusesConflicts;

    public function __construct(
        protected CardPayments $cards,
        protected SubscriptionInvoiceRepository $invoices,
        protected BillingService $billingService,
        protected BusinessCalendar $calendar,
    ) {}

    /** The provider's page to pay invoice $id by card: its address. */
    public function checkout(Workspace $workspace, int $id): string
    {
        $invoice = $this->invoices->findFor($workspace, $id) ?? abort(404, 'Invoice not found.');

        if (! $invoice->isOpen()) {
            $this->refuse('Only an invoice still to be paid can be paid by card.');
        }

        $this->ensureAvailable();

        return $this->cards->checkout($invoice);
    }

    /** The provider's page to save a card, or another in its place: its address. */
    public function cardSetup(Workspace $workspace): string
    {
        $this->ensureAvailable();

        return $this->cards->cardSetup($workspace);
    }

    /**
     * Back from the provider's page: what its checkout did, settled now if the
     * provider has not reported it yet.
     *
     * @return array{message: string, overview: array}
     */
    public function complete(Workspace $workspace, User $user, string $sessionId): array
    {
        $outcome = $this->cards->finished($sessionId);

        if (! $outcome || $outcome->workspaceId !== $workspace->id) {
            $this->refuse('That checkout is not finished. If you paid, it shows here within a few minutes.');
        }

        return ['message' => $this->cards->settle($outcome), 'overview' => $this->billingService->overview($workspace->fresh(), $user)];
    }

    /**
     * Invoice $id paid with the saved card, now - the business asking (Phase
     * D3). A refusal answers why: declined, or the bank asking the
     * cardholder to confirm it on Stripe's page.
     *
     * @return array{message: string, overview: array}
     */
    public function chargeNow(Workspace $workspace, User $user, int $id): array
    {
        $invoice = $this->invoices->findFor($workspace, $id) ?? abort(404, 'Invoice not found.');

        if (! $invoice->isOpen()) {
            $this->refuse('Only an invoice still to be paid can be paid.');
        }

        $this->ensureAvailable();

        if (! $this->cards->card($workspace)) {
            $this->refuse("There is no saved card. Pay by card on Stripe's page, or by bank transfer.");
        }

        $attempt = $this->cards->charge($invoice, $this->calendar->today($workspace)->toDateString(), now: true)
            ?? $this->refuse('A payment for this invoice is still processing. It shows here as soon as it is through.');

        if (! $attempt->succeeded()) {
            $this->refuse(match ($attempt->status) {
                SubscriptionPayment::ACTION_REQUIRED => "Your bank asks you to confirm this payment: use Pay by Card to confirm it on Stripe's page.",
                SubscriptionPayment::PROCESSING      => 'Your payment is processing. It shows here as soon as it is through.',
                default                              => "{$attempt->card} was declined: {$attempt->note} Pay with another card on Stripe's page, or by bank transfer.",
            });
        }

        return [
            'message'  => "Thank you: invoice {$invoice->number} is paid by {$attempt->card}. Receipt {$invoice->fresh()->receipt_number} is on its way by e-mail.",
            'overview' => $this->billingService->overview($workspace->fresh(), $user),
        ];
    }

    /** @return array{message: string, overview: array} the saved card removed */
    public function removeCard(Workspace $workspace, User $user): array
    {
        $card  = $this->cards->card($workspace);

        if (! $card) {
            $this->refuse('There is no saved card to remove.');
        }

        $label = $this->cards->removeCard($card);

        return [
            'message'  => "{$label} is removed. Pay your invoices by card on Stripe's page, or by bank transfer.",
            'overview' => $this->billingService->overview($workspace->fresh(), $user),
        ];
    }

    private function ensureAvailable(): void
    {
        if (! $this->cards->available()) {
            $this->refuse('Card payments are not available right now. Pay by bank transfer instead.');
        }
    }
}
