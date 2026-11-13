<?php

namespace App\Services\Billing;

use App\Models\Billing\SubscriptionInvoice;
use App\Models\Billing\SubscriptionPayment;
use App\Models\Billing\WorkspaceCard;
use App\Models\User\Workspace;
use App\Repositories\Billing\SubscriptionInvoiceRepository;
use App\Repositories\Billing\SubscriptionPaymentRepository;
use App\Repositories\Billing\WorkspaceCardRepository;
use App\Repositories\User\WorkspaceBillingRepository;
use App\Repositories\User\WorkspaceRepository;
use App\Services\Billing\Payments\CardGateway;
use App\Services\Billing\Payments\CardPaymentError;
use App\Services\Billing\Payments\ChargeResult;
use App\Services\Billing\Payments\CheckoutOutcome;
use App\Services\Billing\Payments\RefundOutcome;
use App\Services\Shared\ActivityLogService;
use App\Support\MinorUnits;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Paying izSuite's invoices by card (Doc 13 section 15 - Phase D2), through
 * the card provider (CardGateway: Stripe): its own page to pay an invoice -
 * which keeps the card - or to save a card; what a finished checkout did,
 * settled once however often it is reported, by the business coming back or
 * the provider's notification; and the business's one saved card.
 *
 * A payment pays its invoice as izSuite confirming a transfer does
 * (SubscriptionBilling::confirm): the receipt, and the plan starting or
 * renewing. One that cannot - its invoice cancelled meanwhile, or not its
 * total - is kept with why, for the admin to refund.
 *
 * Phase D3: the saved card is charged without the business there - by the
 * daily cycle, or when the business asks - each try kept, refused or not.
 */
class CardPayments
{
    public function __construct(
        private CardGateway $gateway,
        private SubscriptionBilling $billing,
        private SubscriptionInvoiceRepository $invoices,
        private SubscriptionPaymentRepository $payments,
        private WorkspaceCardRepository $cards,
        private WorkspaceBillingRepository $billingDetails,
        private WorkspaceRepository $workspaces,
        private SubscriptionRefunds $refunds,
    ) {}

    public function available(): bool
    {
        return $this->gateway->available();
    }

    /** The business's saved card, in the mode payments are taken in - null without one. */
    public function card(Workspace $workspace): ?WorkspaceCard
    {
        $card = $this->cards->for($workspace);

        return $card?->hasCard() && $this->current($card) ? $card : null;
    }

    /** The provider's page to pay invoice $invoice by card: its address. */
    public function checkout(SubscriptionInvoice $invoice): string
    {
        return $this->call(fn () => $this->gateway->checkout($invoice, $this->customer($invoice->workspace), $this->returnUrl()));
    }

    /** The provider's page to save a card - the first, or one in its place: its address. */
    public function cardSetup(Workspace $workspace): string
    {
        return $this->call(fn () => $this->gateway->cardSetup($workspace, $this->customer($workspace), $this->returnUrl()));
    }

    /** What checkout $sessionId did, once finished - null before. */
    public function finished(string $sessionId): ?CheckoutOutcome
    {
        return $this->call(fn () => $this->gateway->finished($sessionId));
    }

    /** The provider's notification: the checkout, payment or refund (Doc 15 Phase C2a) it reports, settled. Throws InvalidNotification. */
    public function notified(string $payload, ?string $signature): void
    {
        $outcome = $this->gateway->notified($payload, $signature);

        if ($outcome instanceof RefundOutcome) {
            $this->refunds->settle($outcome);
        } elseif ($outcome) {
            $this->settle($outcome);
        }
    }

    /**
     * Charges the business's saved card for $invoice without it there (Phase
     * D3), on business day $day - once a day, unless $now: the business asks
     * itself. Taken, the invoice is paid as settle() does; refused, the
     * attempt is kept with why. Null when there is nothing to try: no card,
     * card payments off, tried today, one still processing, or - unless
     * $now, which answers 502 - the provider not answering.
     */
    public function charge(SubscriptionInvoice $invoice, string $day, bool $now = false): ?SubscriptionPayment
    {
        $workspace = $invoice->workspace;
        $card      = $this->card($workspace);

        if (! $card || ! $invoice->isOpen() || ! $this->gateway->available()
            || (! $now && $this->payments->attemptedOn($invoice, $day)) || $this->payments->pendingFor($invoice)) {
            return null;
        }

        $key       = "izsuite-charge-{$invoice->id}-".($now ? Str::uuid() : $day);

        try {
            $result = $this->gateway->charge($invoice, $card, $key);
        } catch (CardPaymentError $e) {
            if ($now) {
                $this->call(fn () => throw $e);
            }

            report($e);

            return null;
        }

        if ($result->status === ChargeResult::SUCCEEDED) {
            $this->settle(new CheckoutOutcome($workspace->id, $invoice->id, $card->customer_id, $card->livemode, [
                'id' => $card->card_id, 'brand' => $card->brand, 'last4' => $card->last4, 'exp_month' => $card->exp_month, 'exp_year' => $card->exp_year,
            ], $result->paymentRef, $result->amountMinor, $result->currency), $day);

            return $this->payments->find($this->gateway->name(), $result->paymentRef);
        }

        $attempt   = $this->payments->create([
            'workspace_id' => $workspace->id, 'subscription_invoice_id' => $invoice->id, 'provider' => $this->gateway->name(),
            'reference'    => $result->paymentRef ?? $key, 'currency' => $invoice->currency, 'amount' => $invoice->total,
            'status'       => $result->status, 'attempted_on' => $day, 'card' => $card->label(), 'note' => $result->message,
        ]);

        if ($result->status !== ChargeResult::PROCESSING) {
            ActivityLogService::record('updated', "Charging {$card->label()} for invoice {$invoice->number} did not go through: {$result->message}", $workspace, ['source' => 'system']);
        }

        return $attempt;
    }

    /** The saved card's last try for $invoice when it did not go through - null when it did, or there was none. */
    public function lastFailure(SubscriptionInvoice $invoice): ?SubscriptionPayment
    {
        $last = $this->payments->latestFor($invoice);

        return $last && ! $last->succeeded() ? $last : null;
    }

    /**
     * What a finished checkout did, done once: the payment kept - made on
     * business day $attemptedOn when the saved card was charged - and its
     * invoice paid, the card saved. Answers as a sentence.
     */
    public function settle(CheckoutOutcome $outcome, ?string $attemptedOn = null): string
    {
        $workspace = $this->workspaces->find($outcome->workspaceId)
            ?? throw new CardPaymentError("There is no business {$outcome->workspaceId} for this checkout.");

        if (! $outcome->isPayment()) {
            $label = $this->keepCard($workspace, $outcome);

            return "{$label} is saved for your subscription payments.";
        }

        if (! $outcome->paymentRef) {
            throw new CardPaymentError('The finished checkout names no payment.');
        }

        return DB::transaction(function () use ($workspace, $outcome, $attemptedOn) {
            $invoice = $this->invoices->lock($outcome->invoiceId);
            $known   = $this->payments->find($this->gateway->name(), $outcome->paymentRef);

            // Reported before - unless it was still processing then.
            if ($known && ! in_array($known->status, [SubscriptionPayment::PROCESSING, SubscriptionPayment::ACTION_REQUIRED], true)) {
                return $invoice?->isPaid() ? "Invoice {$invoice->number} is paid. Thank you." : 'This payment is recorded already.';
            }

            $label   = $outcome->card ? WorkspaceCard::describe($outcome->card['brand'], $outcome->card['last4']) : 'Card';
            $amount  = MinorUnits::toDecimal((int) $outcome->amountMinor, (string) $outcome->currency);
            $why     = match (true) {
                ! $invoice || $invoice->workspace_id !== $workspace->id => 'The invoice it names is not this business\'s.',
                $invoice->isPaid()                                       => "Invoice {$invoice->number} was paid already when it arrived.",
                ! $invoice->isOpen()                                     => "Invoice {$invoice->number} was cancelled before it arrived.",
                $outcome->currency !== $invoice->currency || $amount !== (string) $invoice->total => "It was {$outcome->currency} {$amount}; invoice {$invoice->number} is for {$invoice->currency} {$invoice->total}.",
                default                                                  => null,
            };

            $payment = [
                'workspace_id'            => $workspace->id,
                'subscription_invoice_id' => $invoice?->workspace_id === $workspace->id ? $invoice->id : null,
                'provider'                => $this->gateway->name(),
                'reference'               => $outcome->paymentRef,
                'currency'                => $outcome->currency,
                'amount'                  => $amount,
                'status'                  => SubscriptionPayment::SUCCEEDED,
                'card'                    => $label,
                'note'                    => $why ? "Not applied: {$why} Refund it from its invoice." : null,
            ];

            $known ? $this->payments->update($known, $payment) : $this->payments->create([...$payment, 'attempted_on' => $attemptedOn]);

            if ($outcome->card) {
                $this->keepCard($workspace, $outcome);
            }

            if ($why) {
                report(new CardPaymentError("Card payment {$outcome->paymentRef} was not applied: {$why}"));

                return 'Your card payment arrived, but could not pay this invoice. izSuite will refund it.';
            }

            $this->billing->confirm($invoice, null, ['paid_with' => $label, 'reference' => $outcome->paymentRef]);

            ActivityLogService::record('updated', "Paid invoice {$invoice->number} by card ({$label})", $workspace, ['source' => 'system']);

            return "Thank you: invoice {$invoice->number} is paid by {$label}. Receipt {$invoice->fresh()->receipt_number} is on its way by e-mail.";
        });
    }

    /** The saved card taken off the provider and out of izSuite; the customer stays for the next. Answers its label. */
    public function removeCard(WorkspaceCard $card): string
    {
        $this->call(fn () => $this->gateway->removeCard($card->card_id));

        $label = $card->label();

        $this->cards->save($card->workspace, ['card_id' => null, 'brand' => null, 'last4' => null, 'exp_month' => null, 'exp_year' => null]);

        ActivityLogService::record('deleted', "Removed {$label} from subscription payments", $card->workspace, ['source' => 'api']);

        return $label;
    }

    /**
     * $outcome's card as the business's one card: the one before taken off the
     * provider once this commits, and the change logged. Answers its label.
     */
    private function keepCard(Workspace $workspace, CheckoutOutcome $outcome): string
    {
        $card   = $outcome->card ?? throw new CardPaymentError('The checkout saved no card.');
        $label  = WorkspaceCard::describe($card['brand'], $card['last4']);
        $before = $this->cards->for($workspace);

        if ($before?->card_id === $card['id']) {
            return $label;
        }

        $old    = $before?->hasCard() && $before->livemode === $outcome->livemode ? $before : null;

        $this->cards->save($workspace, [
            'provider'  => $this->gateway->name(), 'livemode' => $outcome->livemode, 'customer_id' => $outcome->customerId,
            'card_id'   => $card['id'], 'brand' => $card['brand'], 'last4' => $card['last4'], 'exp_month' => $card['exp_month'], 'exp_year' => $card['exp_year'],
        ]);

        if ($old) {
            DB::afterCommit(function () use ($old) {
                try {
                    $this->gateway->removeCard($old->card_id);
                } catch (CardPaymentError $e) {
                    report($e);
                }
            });
        }

        ActivityLogService::record('updated', $old ? "Replaced {$old->label()} with {$label} for subscription payments" : "Saved {$label} for subscription payments", $workspace, ['source' => 'system']);

        return $label;
    }

    /** The business's customer at the provider - made the first time, and again in the other mode. */
    private function customer(Workspace $workspace): string
    {
        $card    = $this->cards->for($workspace);

        if ($card && $this->current($card)) {
            return $card->customer_id;
        }

        $details = $this->billingDetails->for($workspace);
        $id      = $this->gateway->customer($workspace, $details?->name ?? $workspace->name, $details?->email ?? $workspace->user?->email);

        $this->cards->save($workspace, [
            'provider' => $this->gateway->name(), 'livemode' => $this->gateway->live(), 'customer_id' => $id,
            'card_id'  => null, 'brand' => null, 'last4' => null, 'exp_month' => null, 'exp_year' => null,
        ]);

        return $id;
    }

    /** Whether $card is of the provider and mode payments are taken in now. */
    private function current(WorkspaceCard $card): bool
    {
        return $card->provider === $this->gateway->name() && $card->livemode === $this->gateway->live();
    }

    /** Where the provider sends the business back: its Subscription page. */
    private function returnUrl(): string
    {
        return rtrim((string) config('app.frontend_url'), '/').'/settings?tab=subscription';
    }

    /** The provider asked: a provider that cannot be reached or refuses answers the business plainly, the details logged. */
    private function call(callable $call): mixed
    {
        try {
            return $call();
        } catch (CardPaymentError $e) {
            report($e);

            throw new HttpResponseException(response()->json(['message' => 'Card payments are not available right now. Please try again, or pay by bank transfer.'], 502));
        }
    }
}
