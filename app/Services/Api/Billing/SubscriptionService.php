<?php

namespace App\Services\Api\Billing;

use App\Models\Billing\SubscriptionInvoice;
use App\Models\Billing\WorkspaceSubscription;
use App\Models\User\User;
use App\Models\User\Workspace;
use App\Repositories\Billing\PackageRepository;
use App\Repositories\Billing\SubscriptionInvoiceRepository;
use App\Repositories\Billing\WorkspaceSubscriptionRepository;
use App\Repositories\User\WorkspaceBillingRepository;
use App\Services\Api\Sales\RefusesConflicts;
use App\Services\Billing\SubscriptionBilling;
use App\Services\Billing\SubscriptionCycle;
use App\Services\Billing\SubscriptionPdf;
use App\Services\Shared\ActivityLogService;
use App\Services\Shared\BusinessCalendar;
use App\Services\User\Apps\SyncAppsWithPackage;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * A business subscribing to a paid plan (Doc 13 Phase C1), paid by bank
 * transfer: with a trial the plan starts at once and its first invoice is
 * due as the trial ends; without one, izSuite issues the invoice and the
 * plan starts when izSuite confirms the payment. An invoice not yet paid
 * can be withdrawn, and takes the business's transfer reference. One trial
 * per business.
 *
 * Cancelling (Phase C2a): an active subscription runs to the end of its paid
 * period, then the business is on Billing Starter - undone until then; a
 * trial, or one in grace, ends at once. Changing plan comes with Phase C2b.
 */
class SubscriptionService
{
    use RefusesConflicts;

    public function __construct(
        protected SubscriptionBilling $billing,
        protected SubscriptionCycle $cycle,
        protected BillingService $billingService,
        protected SubscriptionPdf $pdf,
        protected WorkspaceSubscriptionRepository $subscriptions,
        protected SubscriptionInvoiceRepository $invoices,
        protected PackageRepository $packages,
        protected WorkspaceBillingRepository $billingDetails,
        protected BusinessCalendar $calendar,
        protected SyncAppsWithPackage $sync,
    ) {}

    /** @return array{message: string, overview: array} */
    public function cancel(Workspace $workspace, User $user): array
    {
        $subscription = $this->subscriptions->for($workspace);
        $plan         = $subscription?->package?->name;

        if ($subscription?->status === WorkspaceSubscription::ACTIVE && ! $subscription->cancels_at_period_end) {
            $end     = $subscription->period_ends_on;

            DB::transaction(function () use ($workspace) {
                $this->subscriptions->save($workspace, ['cancels_at_period_end' => true]);

                // Its renewal, if issued, is not to be paid now.
                if (($open = $this->invoices->openFor($workspace)) && $open->kind === SubscriptionInvoice::RENEWAL) {
                    $this->billing->void($open);
                }
            });

            ActivityLogService::record('cancelled', "Cancelled the {$plan} subscription; it ends after {$end->format('j M Y')}", $workspace, ['source' => 'api']);

            $message = "{$plan} stays until {$end->format('j M Y')}. Then your business moves to Billing Starter, the free plan.";
        } elseif (in_array($subscription?->status, [WorkspaceSubscription::TRIALING, WorkspaceSubscription::GRACE], true)) {
            $this->cycle->end($subscription, 'cancelled');

            $message = "{$plan} is cancelled. Your business is on Billing Starter, the free plan.";
        } else {
            $this->refuse($subscription?->status === WorkspaceSubscription::AWAITING_PAYMENT
                ? 'Your plan has not started yet: withdraw its invoice instead.'
                : 'There is no paid subscription to cancel.');
        }

        return ['message' => $message, 'overview' => $this->billingService->overview($workspace->fresh(), $user)];
    }

    /** @return array{message: string, overview: array} the cancellation undone, before the period ends - its renewal issued if it is due */
    public function resume(Workspace $workspace, User $user): array
    {
        $subscription = $this->subscriptions->for($workspace);

        if ($subscription?->status !== WorkspaceSubscription::ACTIVE || ! $subscription->cancels_at_period_end) {
            $this->refuse('There is no cancellation to undo.');
        }

        $this->subscriptions->save($workspace, ['cancels_at_period_end' => false]);
        $this->cycle->renewIfDue($subscription->refresh());

        ActivityLogService::record('updated', "Kept the {$subscription->package->name} subscription", $workspace, ['source' => 'api']);

        return [
            'message'  => "{$subscription->package->name} carries on. Its renewal invoice comes ".SubscriptionCycle::RENEWAL_DAYS.' days before the period ends.',
            'overview' => $this->billingService->overview($workspace->fresh(), $user),
        ];
    }

    /** @return array{message: string, overview: array} */
    public function subscribe(Workspace $workspace, User $user, string $packageKey, string $cycle): array
    {
        $subscription = $this->subscriptions->for($workspace);

        if ($subscription?->isCurrent()) {
            $this->refuse('Your business already has a subscription. Changing it comes soon.');
        }

        $package      = $this->packages->findOfferedByKey($packageKey);

        if (! $package || $package->isFree()) {
            throw ValidationException::withMessages(['package' => 'Choose one of the paid plans on offer.']);
        }

        if ($this->billing->price($package, $cycle) === null) {
            throw ValidationException::withMessages(['cycle' => "{$package->name}'s ".($cycle === WorkspaceSubscription::YEARLY ? 'yearly' : 'monthly').' price has not been announced yet.']);
        }

        if (! $this->billingDetails->for($workspace)) {
            throw ValidationException::withMessages(['billing' => 'Add your billing details first: the invoice is made out to them.']);
        }

        // One trial per business, and only for a plan it is not on already.
        $today        = $this->calendar->today($workspace);
        $trial        = $package->trial_days && ! $subscription?->trial_starts_on && $workspace->package_id !== $package->id;

        $invoice      = DB::transaction(function () use ($workspace, $user, $package, $cycle, $today, $trial) {
            if (! $trial) {
                $this->subscriptions->save($workspace, ['package_id' => $package->id, 'cycle' => $cycle, 'status' => WorkspaceSubscription::AWAITING_PAYMENT, 'period_starts_on' => null, 'period_ends_on' => null]);

                return $this->billing->issue($workspace, $package, $cycle);
            }

            $last = $today->addDays($package->trial_days - 1);

            $this->subscriptions->save($workspace, [
                'package_id'      => $package->id, 'cycle' => $cycle, 'status' => WorkspaceSubscription::TRIALING,
                'trial_starts_on' => $today->toDateString(), 'trial_ends_on' => $last->toDateString(), 'period_starts_on' => null, 'period_ends_on' => null,
            ]);
            $this->sync->changePackage($workspace, $package, $user);

            return $this->billing->issue($workspace, $package, $cycle, $last->addDay(), $last);
        });

        $what         = $trial ? "Started a {$package->trial_days}-day trial of {$package->name}" : "Subscribed to {$package->name}";

        ActivityLogService::record('created', "{$what} ({$cycle}); invoice {$invoice->number} issued", $workspace, ['source' => 'api']);

        return [
            'message'  => $trial
                ? "Your {$package->trial_days}-day {$package->name} trial has started. Invoice {$invoice->number} is due by {$invoice->due_on->format('j M Y')}."
                : "Invoice {$invoice->number} is issued. {$package->name} starts as soon as izSuite confirms your bank transfer.",
            'overview' => $this->billingService->overview($workspace->fresh(), $user),
        ];
    }

    /** @return array{message: string, overview: array} the invoice waiting for the first payment withdrawn - and the subscription with it */
    public function withdraw(Workspace $workspace, User $user): array
    {
        $invoice = $this->invoices->openFor($workspace);

        if ($this->subscriptions->for($workspace)?->status !== WorkspaceSubscription::AWAITING_PAYMENT || ! $invoice) {
            $this->refuse('There is no invoice waiting for a first payment to withdraw.');
        }

        $this->billing->void($invoice);

        ActivityLogService::record('cancelled', "Withdrew invoice {$invoice->number} for {$invoice->package_name}", $workspace, ['source' => 'api']);

        return ['message' => "Invoice {$invoice->number} is withdrawn. Choose a plan again whenever you are ready.", 'overview' => $this->billingService->overview($workspace->fresh(), $user)];
    }

    /** The reference the business gave its bank transfer, so izSuite can match the payment - on an open invoice. */
    public function saveReference(Workspace $workspace, int $id, ?string $reference): SubscriptionInvoice
    {
        $invoice = $this->find($workspace, $id);

        if (! $invoice->isOpen()) {
            $this->refuse('Only an invoice still to be paid takes a transfer reference.');
        }

        $this->invoices->update($invoice, ['transfer_reference' => $reference]);

        ActivityLogService::record('updated', ($reference ? 'Added the transfer reference to' : 'Removed the transfer reference from')." invoice {$invoice->number}", $workspace, ['source' => 'api']);

        return $invoice;
    }

    public function history(Workspace $workspace, int $perPage): LengthAwarePaginator
    {
        return $this->invoices->pageFor($workspace, $perPage);
    }

    /** @return array{0: string, 1: string} an invoice's PDF - or its receipt's, once paid - and its file name */
    public function pdf(Workspace $workspace, int $id, bool $receipt): array
    {
        $invoice = $this->find($workspace, $id);

        abort_if($receipt && ! $invoice->isPaid(), 404, 'This invoice has no receipt yet.');

        return $this->pdf->render($invoice, $receipt);
    }

    /** Another business's invoice answers like one that does not exist. */
    private function find(Workspace $workspace, int $id): SubscriptionInvoice
    {
        return $this->invoices->findFor($workspace, $id) ?? abort(404, 'Invoice not found.');
    }
}
