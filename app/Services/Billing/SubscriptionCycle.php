<?php

namespace App\Services\Billing;

use App\Mail\SubscriptionNoticeMail;
use App\Models\Billing\SubscriptionInvoice;
use App\Models\Billing\WorkspaceSubscription;
use App\Repositories\Billing\PackageRepository;
use App\Repositories\Billing\SubscriptionInvoiceRepository;
use App\Repositories\Billing\WorkspaceSubscriptionRepository;
use App\Services\Shared\ActivityLogService;
use App\Services\Shared\BusinessCalendar;
use App\Services\User\Apps\SyncAppsWithPackage;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * A subscription's days (Doc 13 sections 12, 13 and 19 - Phase C2a), moved
 * on by the hourly `subscriptions:advance`, each business by its own
 * calendar day:
 *
 * - seven days before a paid period ends, its renewal invoice is issued,
 *   due on the period's last day;
 * - a period ending paid rolls into the next; ending unpaid, the plan's
 *   grace starts the next day, with an e-mail every day of it saying how
 *   many days are left - 4 on the first of 5, the last day on the fifth -
 *   and when it runs out the plan is cancelled and the business is on
 *   Billing Starter (Phase B2's move down). Without grace days, that
 *   happens as the period ends; a cancellation waits for it too;
 * - a trial reminds three days and one day before it ends and, its
 *   invoice unpaid, goes back to Billing Starter the day after.
 *
 * Each step happens once: a renewal is issued once a period, and the daily
 * e-mails once a day (last_notice_on).
 */
class SubscriptionCycle
{
    /** Days before a period ends that its renewal invoice is issued. */
    public const RENEWAL_DAYS    = 7;

    /** Days before a trial ends that it reminds. */
    public const TRIAL_REMINDERS = [3, 1];

    public function __construct(
        private WorkspaceSubscriptionRepository $subscriptions,
        private SubscriptionInvoiceRepository $invoices,
        private PackageRepository $packages,
        private SubscriptionBilling $billing,
        private BusinessCalendar $calendar,
        private SyncAppsWithPackage $sync,
    ) {}

    /** Moves every running subscription on to its business's today; answers with how many there were. */
    public function run(): int
    {
        $subscriptions = $this->subscriptions->running();

        foreach ($subscriptions as $subscription) {
            $this->advance($subscription);
        }

        return $subscriptions->count();
    }

    public function advance(WorkspaceSubscription $subscription): void
    {
        $today = $this->today($subscription);

        match ($subscription->status) {
            WorkspaceSubscription::TRIALING => $this->trial($subscription, $today),
            WorkspaceSubscription::ACTIVE   => $this->active($subscription, $today),
            WorkspaceSubscription::GRACE    => $this->grace($subscription, $today),
            default                         => null,
        };
    }

    /**
     * The renewal for the period after this one, once it is due - from
     * seven days before the period ends. Issued once, on the plan and cycle
     * the subscription has; none for a subscription ending by cancellation.
     */
    public function renewIfDue(WorkspaceSubscription $subscription): ?SubscriptionInvoice
    {
        $end = $subscription->period_ends_on?->toImmutable();

        if ($subscription->status !== WorkspaceSubscription::ACTIVE || $subscription->cancels_at_period_end || ! $end
            || $this->today($subscription)->lt($end->subDays(self::RENEWAL_DAYS))) {
            return null;
        }

        // An upgrade waiting for payment starts a period of its own; unpaid, it lapses as this one ends.
        if ($this->invoices->openFor($subscription->workspace)?->kind === SubscriptionInvoice::UPGRADE) {
            return null;
        }

        return $this->renewal($subscription);
    }

    /**
     * Ends the subscription now - $reason: trial, grace, cancelled or ended
     * - and puts the business on Billing Starter; its invoice still to be
     * paid is cancelled. Logged, and the business is told.
     */
    public function end(WorkspaceSubscription $subscription, string $reason): void
    {
        $workspace = $subscription->workspace;
        $plan      = $subscription->package->name;

        DB::transaction(function () use ($workspace) {
            if ($open = $this->invoices->openFor($workspace)) {
                $this->invoices->update($open, ['status' => SubscriptionInvoice::VOID, 'voided_at' => now()]);
            }

            $this->subscriptions->save($workspace, [
                'status' => WorkspaceSubscription::CANCELLED, 'grace_ends_on' => null, 'cancels_at_period_end' => false, 'scheduled_package_id' => null, 'scheduled_cycle' => null, 'last_notice_on' => null,
            ]);

            $starter = $this->packages->starter();

            if ($starter && $workspace->package_id !== $starter->id) {
                $this->sync->changePackage($workspace, $starter);
            }
        });

        ActivityLogService::record('cancelled', match ($reason) {
            'trial'              => "The {$plan} trial ended unpaid; the business is back on Billing Starter",
            'grace'              => "The {$plan} subscription was not renewed in its grace; the business is back on Billing Starter",
            'cancelled'          => "The {$plan} subscription ended as cancelled; the business is back on Billing Starter",
            default              => "The {$plan} subscription ended unpaid; the business is back on Billing Starter",
        }, $workspace, ['source' => 'system']);

        $this->billing->notice($workspace, SubscriptionNoticeMail::ENDED, ['plan' => $plan, 'reason' => $reason]);
    }

    /** Reminds as the trial nears its end; the day after, unpaid, back to Billing Starter. */
    private function trial(WorkspaceSubscription $subscription, CarbonImmutable $today): void
    {
        $last    = $subscription->trial_ends_on->toImmutable();

        if ($today->gt($last)) {
            $this->end($subscription, 'trial');

            return;
        }

        $left    = $this->daysFrom($today, $last);
        $invoice = $this->invoices->openFor($subscription->workspace);

        if ($invoice && in_array($left, self::TRIAL_REMINDERS, true)) {
            $this->notify($subscription, $today, SubscriptionNoticeMail::TRIAL_ENDING, [
                'days_left' => $left, 'trial_ends_on' => $last->format('j M Y'), ...$this->invoiceDetails($invoice),
            ]);
        }
    }

    private function active(WorkspaceSubscription $subscription, CarbonImmutable $today): void
    {
        $end = $subscription->period_ends_on?->toImmutable();

        if (! $end) {
            return;
        }

        if ($today->gt($end)) {
            $this->periodEnded($subscription, $end, $today);

            return;
        }

        $this->renewIfDue($subscription);
    }

    /**
     * Paid: the next period. Cancelled, or no grace: the end. Otherwise
     * grace, from the day after. A move down waiting for this day happens
     * first, paid or not; an upgrade not paid by now lapses.
     */
    private function periodEnded(WorkspaceSubscription $subscription, CarbonImmutable $end, CarbonImmutable $today): void
    {
        $workspace = $subscription->workspace;

        if ($subscription->cancels_at_period_end) {
            $this->end($subscription, 'cancelled');

            return;
        }

        // Its credit was for days now gone.
        if (($upgrade = $this->invoices->openFor($workspace)) && $upgrade->kind === SubscriptionInvoice::UPGRADE) {
            $this->invoices->update($upgrade, ['status' => SubscriptionInvoice::VOID, 'voided_at' => now()]);
        }

        if ($to = $subscription->scheduledPackage) {
            $from         = $subscription->package->name;

            DB::transaction(function () use ($subscription, $workspace, $to) {
                $this->subscriptions->save($workspace, ['package_id' => $to->id, 'cycle' => $subscription->scheduled_cycle, 'scheduled_package_id' => null, 'scheduled_cycle' => null]);
                $this->sync->changePackage($workspace, $to);
            });

            ActivityLogService::record('updated', "Moved from {$from} to {$to->name}, {$subscription->scheduled_cycle}, as its paid period ended", $workspace, ['source' => 'system']);

            $subscription = $subscription->refresh();
        }

        $renewal   = $this->invoices->renewalFor($workspace, $end->addDay()->toDateString());

        if ($renewal?->isPaid()) {
            $this->subscriptions->save($workspace, ['period_starts_on' => $renewal->period_starts_on->toDateString(), 'period_ends_on' => $renewal->period_ends_on->toDateString(), 'last_notice_on' => null]);

            ActivityLogService::record('updated', "{$subscription->package->name} renewed until {$renewal->period_ends_on->format('j M Y')}", $workspace, ['source' => 'system']);

            // Several periods paid ahead roll on in turn.
            $this->active($subscription->refresh(), $today);

            return;
        }

        $graceDays = (int) $subscription->package->grace_days;

        if ($graceDays < 1) {
            $this->end($subscription, 'ended');

            return;
        }

        // Its renewal, if the days before it ended went by without one, is issued as the grace starts.
        $this->renewal($subscription);
        $this->subscriptions->save($workspace, ['status' => WorkspaceSubscription::GRACE, 'grace_ends_on' => $end->addDays($graceDays)->toDateString(), 'last_notice_on' => null]);

        ActivityLogService::record('updated', "The {$subscription->package->name} period ended unpaid; its grace runs until {$end->addDays($graceDays)->format('j M Y')}", $workspace, ['source' => 'system']);

        $this->grace($subscription->refresh(), $today);
    }

    /** An e-mail each day of grace, with the days left; when it has run out, the end. */
    private function grace(WorkspaceSubscription $subscription, CarbonImmutable $today): void
    {
        $last = $subscription->grace_ends_on->toImmutable();

        if ($today->gt($last)) {
            $this->end($subscription, 'grace');

            return;
        }

        $this->notify($subscription, $today, SubscriptionNoticeMail::GRACE, [
            'days_left' => $this->daysFrom($today, $last), 'last_day' => $last->format('j M Y'), 'cancels_on' => $last->addDay()->format('j M Y'),
            ...$this->invoiceDetails($this->renewal($subscription)),
        ]);
    }

    /**
     * The renewal for the period after the current one - issued now if it
     * has not been, due on the period's last day - on the plan and cycle it
     * moves to then, if a move down waits for it.
     */
    private function renewal(WorkspaceSubscription $subscription): SubscriptionInvoice
    {
        $workspace = $subscription->workspace;
        $end       = $subscription->period_ends_on->toImmutable();

        return $this->invoices->renewalFor($workspace, $end->addDay()->toDateString())
            ?? $this->billing->issue(
                $workspace,
                $subscription->scheduledPackage ?? $subscription->package,
                $subscription->scheduled_cycle ?? $subscription->cycle,
                $end->addDay(),
                $end,
                SubscriptionInvoice::RENEWAL,
            );
    }

    /** A notice, once a day. */
    private function notify(WorkspaceSubscription $subscription, CarbonImmutable $today, string $kind, array $details): void
    {
        if ($subscription->last_notice_on?->toDateString() === $today->toDateString()) {
            return;
        }

        $this->subscriptions->save($subscription->workspace, ['last_notice_on' => $today->toDateString()]);
        $this->billing->notice($subscription->workspace, $kind, ['plan' => $subscription->package->name, ...$details]);
    }

    /** @return array{invoice: string, total: string} */
    private function invoiceDetails(SubscriptionInvoice $invoice): array
    {
        return ['invoice' => $invoice->number, 'total' => $invoice->currency.' '.number_format((float) $invoice->total, 2)];
    }

    /** The business's today, as a date like the subscription's own. */
    private function today(WorkspaceSubscription $subscription): CarbonImmutable
    {
        return CarbonImmutable::parse($this->calendar->today($subscription->workspace)->toDateString());
    }

    private function daysFrom(CarbonImmutable $from, CarbonImmutable $to): int
    {
        return (int) round($from->diffInDays($to, false));
    }
}
