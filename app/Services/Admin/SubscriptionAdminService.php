<?php

namespace App\Services\Admin;

use App\Mail\AccountNoticeMail;
use App\Models\Billing\SubscriptionInvoice;
use App\Models\Billing\SubscriptionPayment;
use App\Models\Billing\WorkspaceSubscription;
use App\Models\User\User;
use App\Models\User\Workspace;
use App\Repositories\Admin\BusinessRepository;
use App\Repositories\Admin\SubscriptionAdminRepository;
use App\Repositories\Billing\PackageRepository;
use App\Repositories\Billing\SubscriptionInvoiceRepository;
use App\Repositories\Billing\WorkspaceSubscriptionRepository;
use App\Services\Billing\CardPayments;
use App\Services\Billing\SubscriptionBilling;
use App\Services\Billing\SubscriptionChanges;
use App\Services\Billing\SubscriptionCredits;
use App\Services\Billing\SubscriptionCycle;
use App\Services\Shared\ActivityLogService;
use App\Services\Shared\BusinessCalendar;
use App\Services\Shared\Notifications\Notice;
use App\Services\Shared\Notifications\Notifier;
use App\Support\SubscriptionStatus;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/**
 * Admin > Subscriptions (Doc 15 section 15.13): the list, one subscription
 * with its history, and izSuite's changes to it - a plan change, a trial or
 * grace extended, a cancellation scheduled or a scheduled change undone, an
 * invoice resent, a failed card payment retried. Each change goes through
 * Doc 13's own billing, keeps the admin's reason in the audit log, and is
 * told to the business's owner.
 */
class SubscriptionAdminService
{
    public function __construct(
        protected SubscriptionAdminRepository $subscriptions,
        protected WorkspaceSubscriptionRepository $workspaceSubscriptions,
        protected SubscriptionInvoiceRepository $invoices,
        protected PackageRepository $packages,
        protected BusinessRepository $businesses,
        protected SubscriptionBilling $billing,
        protected SubscriptionChanges $changes,
        protected SubscriptionCycle $cycle,
        protected CardPayments $cards,
        protected BusinessCalendar $calendar,
        protected AdminAuditLogService $audit,
        protected Notifier $notifier,
        protected SubscriptionCredits $credits,
    ) {}

    public function page(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        return $this->subscriptions->page($filters, $perPage);
    }

    /** The paid plans, lowest first: the filter's, and the ones a plan change can move to. The free plan has no subscription of its own. */
    public function packages(): Collection
    {
        return $this->packages->allByRank()->reject(fn ($package) => $package->isFree())->values();
    }

    /** @return array<string, mixed> what a subscription's page shows */
    public function details(int $id): array
    {
        $subscription = $this->find($id);
        $workspace    = $subscription->workspace;

        return [
            'subscription' => $subscription,
            'openInvoice'  => $this->invoices->openFor($workspace),
            'invoices'     => $this->subscriptions->invoicesFor($workspace),
            'card'         => $this->cards->card($workspace),
            'canRetry'     => $this->retryProblem($subscription) === null,
            'activity'     => $this->businesses->adminActivity($workspace),
            'plans'        => $this->packages(),
            // izSuite's credit not used yet, in the plan's currency (Doc 15 Phase C2b).
            'credit'       => $this->credits->balance($workspace, $subscription->paysIn()),
        ];
    }

    /** Up - its invoice issued, the plan changing once paid - or down at the paid period's end, as the business itself would. */
    public function changePlan(int $id, User $admin, int $packageId, string $cycle, string $reason): string
    {
        $workspace = $this->find($id)->workspace;
        $to        = $this->packages->findOrFail($packageId);

        $this->audit->withReason($reason);

        $change    = $this->changes->preview($workspace, $to, $cycle);
        $this->changes->apply($workspace, $to, $cycle);

        if ($change['kind'] === SubscriptionChanges::UP) {
            // Just issued: the latest - paid already when izSuite's credit covered it (Doc 15 Phase C2b).
            $invoice = $this->subscriptions->invoicesFor($workspace, 1)->first();

            if ($invoice->isPaid()) {
                $this->tellOwner($workspace, "Your plan is now {$to->name}", [
                    "izSuite has moved {$workspace->name} to {$to->name}, {$cycle}. Invoice {$invoice->number} is paid with izSuite's credit, so the plan has changed already.",
                    "Why: {$reason}",
                ]);

                return "Invoice {$invoice->number} is paid with izSuite's credit: {$workspace->name} is on {$to->name} now.";
            }

            $this->tellOwner($workspace, "izSuite is moving your plan to {$to->name}", [
                "izSuite has started moving {$workspace->name} to {$to->name}, {$cycle}. Invoice {$invoice->number}, for {$invoice->currency} {$invoice->total}, is on its way: the plan changes as soon as it is paid.",
                "Why: {$reason}",
            ]);

            return "Invoice {$invoice->number} is issued. {$workspace->name} moves to {$to->name} once it is paid.";
        }

        $on = Carbon::parse($change['effective_on'])->format('j M Y');

        $this->tellOwner($workspace, "Your plan moves to {$to->name} on {$on}", [
            "izSuite has scheduled {$workspace->name}'s move to {$to->name}, {$cycle}, on {$on}, when its paid period ends. Until then it keeps its plan.",
            "Why: {$reason}",
        ]);

        return "{$workspace->name} moves to {$to->name}, {$cycle}, on {$on}.";
    }

    /** More days of trial; its invoice moves with it - the paid period starting the day after, due on its last day. */
    public function extendTrial(int $id, User $admin, int $days, string $reason): string
    {
        $subscription = $this->find($id);

        $this->refuseUnless($subscription->status === WorkspaceSubscription::TRIALING, 'Only a subscription on trial has a trial to extend.');

        $workspace    = $subscription->workspace;
        $from         = $subscription->trial_ends_on->toImmutable();
        $until        = $from->addDays($days);

        $this->audit->withReason($reason);

        DB::transaction(function () use ($subscription, $workspace, $until) {
            $this->workspaceSubscriptions->save($workspace, ['trial_ends_on' => $until->toDateString(), 'last_notice_on' => null]);

            if (($open = $this->invoices->openFor($workspace)) && $open->kind === SubscriptionInvoice::NEW) {
                $this->billing->void($open);
                $this->billing->issue($workspace, $subscription->package, $subscription->cycle, $until->addDay(), $until, SubscriptionInvoice::NEW, (string) $open->credit);
            }
        });

        ActivityLogService::record('updated', "Extended the {$subscription->package->name} trial of business \"{$workspace->name}\" by {$days} days, until {$until->format('j M Y')}", $workspace, [
            'changes' => ['trial_ends_on' => ['from' => $from->toDateString(), 'to' => $until->toDateString()]],
        ], $admin);

        $this->tellOwner($workspace, "Your trial now ends on {$until->format('j M Y')}", [
            "izSuite has extended {$workspace->name}'s {$subscription->package->name} trial by {$days} days, until {$until->format('j M Y')}. Its invoice is issued again, due that day.",
            "Why: {$reason}",
        ]);

        return "The trial now ends on {$until->format('j M Y')}.";
    }

    /** More days to pay before the plan ends. */
    public function extendGrace(int $id, User $admin, int $days, string $reason): string
    {
        $subscription = $this->find($id);

        $this->refuseUnless($subscription->status === WorkspaceSubscription::GRACE, 'Only a subscription in its grace period has a grace to extend.');

        $workspace    = $subscription->workspace;
        $from         = $subscription->grace_ends_on->toImmutable();
        $until        = $from->addDays($days);

        $this->audit->withReason($reason);
        $this->workspaceSubscriptions->save($workspace, ['grace_ends_on' => $until->toDateString()]);

        ActivityLogService::record('updated', "Extended the {$subscription->package->name} grace of business \"{$workspace->name}\" by {$days} days, until {$until->format('j M Y')}", $workspace, [
            'changes' => ['grace_ends_on' => ['from' => $from->toDateString(), 'to' => $until->toDateString()]],
        ], $admin);

        $this->tellOwner($workspace, "You have until {$until->format('j M Y')} to pay", [
            "izSuite has given {$workspace->name} {$days} more days to pay its renewal: {$subscription->package->name} stays until {$until->format('j M Y')}.",
            "Why: {$reason}",
        ]);

        return "The grace now ends on {$until->format('j M Y')}.";
    }

    /** An active subscription set to end with its paid period; its renewal, if issued, is not to be paid. */
    public function scheduleCancellation(int $id, User $admin, string $reason): string
    {
        $subscription = $this->find($id);

        $this->refuseUnless($subscription->status === WorkspaceSubscription::ACTIVE && ! $subscription->cancels_at_period_end, 'Only an active subscription not already ending can have its cancellation scheduled.');

        $workspace    = $subscription->workspace;
        $plan         = $subscription->package->name;
        $end          = $subscription->period_ends_on->format('j M Y');

        $this->audit->withReason($reason);

        DB::transaction(function () use ($workspace) {
            $this->workspaceSubscriptions->save($workspace, ['cancels_at_period_end' => true, 'scheduled_package_id' => null, 'scheduled_cycle' => null]);

            if (($open = $this->invoices->openFor($workspace)) && $open->kind === SubscriptionInvoice::RENEWAL) {
                $this->billing->void($open);
            }
        });

        ActivityLogService::record('cancelled', "Scheduled the end of business \"{$workspace->name}\"'s {$plan} subscription, after {$end}", $workspace, null, $admin);

        $this->tellOwner($workspace, "{$plan} ends after {$end}", [
            "izSuite has cancelled {$workspace->name}'s {$plan} subscription. It stays until {$end}; then the business moves to Billing Starter, the free plan, and keeps its records.",
            "Why: {$reason}",
        ]);

        return "{$plan} ends after {$end}; then the business moves to Billing Starter.";
    }

    /** A scheduled cancellation, or a move down waiting for the period's end, undone. */
    public function cancelScheduledChange(int $id, User $admin, string $reason): string
    {
        $subscription = $this->find($id);
        $workspace    = $subscription->workspace;
        $plan         = $subscription->package->name;
        $what         = SubscriptionStatus::of($subscription);

        $this->refuseUnless(in_array($what, [SubscriptionStatus::CANCELLATION_SCHEDULED, SubscriptionStatus::DOWNGRADE_SCHEDULED], true), 'There is no scheduled cancellation or move down to undo.');

        $this->audit->withReason($reason);

        if ($what === SubscriptionStatus::DOWNGRADE_SCHEDULED) {
            $this->changes->undo($workspace);
        } else {
            // Its renewal, if it is due already, issued now as the cycle would have.
            $this->cycle->renewIfDue($this->workspaceSubscriptions->save($workspace, ['cancels_at_period_end' => false]));

            ActivityLogService::record('updated', "Undid the scheduled end of business \"{$workspace->name}\"'s {$plan} subscription", $workspace, null, $admin);
        }

        $this->tellOwner($workspace, "{$plan} carries on", [
            "izSuite has undone the change waiting for the end of {$workspace->name}'s paid period: {$plan} carries on, and renews as before.",
            "Why: {$reason}",
        ]);

        return "{$plan} carries on.";
    }

    /**
     * izSuite's credit for the business (Doc 15 Phase C2b), in its plan's
     * currency: taken off its next invoice before tax - the one after any
     * waiting now - and what is left off the ones after.
     */
    public function addCredit(int $id, User $admin, string $amount, string $reason): string
    {
        [$workspace, $currency, $amount] = $this->creditOf($id, $amount);
        $next     = ($open = $this->invoices->openFor($workspace)) ? "the invoice after {$open->number} (that one is waiting for payment now)" : 'the next invoice';

        $this->audit->withReason($reason);
        $this->credits->grant($workspace, $currency, $amount, $reason, $admin);
        $balance  = $this->credits->balance($workspace, $currency);

        ActivityLogService::record('updated', "Gave business \"{$workspace->name}\" a credit of {$currency} {$amount}", $workspace, [
            'changes' => ['credit' => ['from' => bcsub($balance, $amount, 2), 'to' => $balance]],
        ], $admin);

        $this->tellOwner($workspace, "izSuite has given you a credit of {$currency} {$amount}", [
            "izSuite has given {$workspace->name} a credit of {$currency} {$amount}. It comes off {$next}, before tax; what is left comes off the ones after. Your credit is {$currency} {$balance} now.",
            "Why: {$reason}",
        ]);

        return "{$workspace->name} has {$currency} {$balance} of credit, to come off {$next}.";
    }

    /** Takes back $amount of the business's credit not used yet (Doc 15 Phase C2b) - refused beyond what is left. */
    public function removeCredit(int $id, User $admin, string $amount, string $reason): string
    {
        [$workspace, $currency, $amount] = $this->creditOf($id, $amount);

        $this->audit->withReason($reason);
        $this->credits->remove($workspace, $currency, $amount, $reason, $admin);
        $balance = $this->credits->balance($workspace, $currency);

        ActivityLogService::record('updated', "Took back {$currency} {$amount} of business \"{$workspace->name}\"'s credit", $workspace, [
            'changes' => ['credit' => ['from' => bcadd($balance, $amount, 2), 'to' => $balance]],
        ], $admin);

        $this->tellOwner($workspace, "izSuite has taken back {$currency} {$amount} of your credit", [
            "izSuite has taken back {$currency} {$amount} of {$workspace->name}'s credit, not used yet. Your credit is {$currency} {$balance} now.",
            "Why: {$reason}",
        ]);

        return "{$workspace->name}'s credit is {$currency} {$balance} now.";
    }

    /** @return array{0: Workspace, 1: string, 2: string} the subscription's business, its plan's currency, and $amount to the cent */
    private function creditOf(int $id, string $amount): array
    {
        $subscription = $this->find($id);

        return [$subscription->workspace, $subscription->paysIn(), bcadd($amount, '0', 2)];
    }

    /** The invoice waiting for payment - else the latest - e-mailed again. */
    public function resend(int $id, User $admin): string
    {
        $workspace = $this->find($id)->workspace;
        $invoice   = $this->invoices->openFor($workspace) ?? $this->subscriptions->invoicesFor($workspace, 1)->first();

        $this->refuseUnless($invoice !== null, 'This business has no invoice to resend.');

        $this->billing->resend($invoice);

        ActivityLogService::record('notified', "Resent invoice {$invoice->number} to business \"{$workspace->name}\"", $workspace, null, $admin);

        return "Invoice {$invoice->number} is on its way again.";
    }

    /** The saved card charged again for the invoice it failed on, now. */
    public function retry(int $id, User $admin): string
    {
        $subscription = $this->find($id);

        $this->refuseUnless(($problem = $this->retryProblem($subscription)) === null, (string) $problem);

        $workspace    = $subscription->workspace;
        $invoice      = $this->invoices->openFor($workspace);
        $attempt      = $this->cards->charge($invoice, $this->calendar->today($workspace)->toDateString(), now: true);

        $outcome      = match (true) {
            $attempt === null                                  => 'nothing could be tried',
            $attempt->succeeded()                              => "{$attempt->card} was charged; invoice {$invoice->number} is paid",
            $attempt->status === SubscriptionPayment::PROCESSING => 'the payment is processing; the invoice is paid once the card provider confirms it',
            default                                            => "{$attempt->card} was refused: {$attempt->note}",
        };

        ActivityLogService::record('updated', "Retried the card for invoice {$invoice->number} of business \"{$workspace->name}\": {$outcome}", $workspace, null, $admin);

        return ucfirst($outcome).'.';
    }

    /** Why the saved card cannot be retried now; null when it can. */
    private function retryProblem(WorkspaceSubscription $subscription): ?string
    {
        $workspace = $subscription->workspace;
        $invoice   = $this->invoices->openFor($workspace);

        return match (true) {
            $invoice === null                         => 'There is no invoice waiting for payment.',
            ! $this->cards->available()               => 'Card payments are not set up.',
            $this->cards->card($workspace) === null   => 'The business has no card saved.',
            $this->cards->lastFailure($invoice) === null => 'The card has not failed on this invoice: it is charged on its due day.',
            default                                   => null,
        };
    }

    private function find(int $id): WorkspaceSubscription
    {
        return $this->subscriptions->findForPage($id) ?? abort(404);
    }

    private function refuseUnless(bool $allowed, string $message): void
    {
        abort_unless($allowed, 422, $message);
    }

    /** The owner hears of izSuite's change: in the bell, as a Message from izSuite, and by e-mail. */
    private function tellOwner(Workspace $workspace, string $title, array $lines): void
    {
        $owner = $workspace->user;

        if (! $owner) {
            return;
        }

        $this->notifier->send($workspace, [$owner], new Notice(
            trigger: 'izsuite.subscription',
            key: 'izsuite.subscription.'.$workspace->id.'.'.now()->format('YmdHisv'),
            title: $title,
            body: implode(' ', $lines),
            link: '/settings?tab=subscription',
            subject: $workspace,
        ));

        Mail::to($owner->email)->queue(new AccountNoticeMail($owner->first_name ?: $owner->name, $title, $lines, 'See your subscription', rtrim((string) config('app.frontend_url'), '/').'/settings?tab=subscription'));
    }
}
