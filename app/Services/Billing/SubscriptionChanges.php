<?php

namespace App\Services\Billing;

use App\Models\Billing\Package;
use App\Models\Billing\SubscriptionInvoice;
use App\Models\Billing\WorkspaceSubscription;
use App\Models\User\Workspace;
use App\Repositories\Billing\SubscriptionInvoiceRepository;
use App\Repositories\Billing\WorkspaceSubscriptionRepository;
use App\Services\Shared\ActivityLogService;
use App\Services\Shared\BusinessCalendar;
use Carbon\CarbonImmutable;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Changing an active subscription's plan (Doc 13 section 13 - Phase C2b):
 *
 * - up - a higher plan, or monthly to yearly - at once, its price shown
 *   first less a credit for the unused part of the paid period, worked out
 *   from what that period was paid, by the day. Its invoice issued, the plan
 *   changes when izSuite confirms the payment, for a full period from that
 *   day (SubscriptionBilling::confirm); unpaid, it lapses as the period
 *   ends. From a yearly plan an upgrade stays yearly, so no month is lost;
 * - down - a lower paid plan, or yearly to monthly - at the end of the paid
 *   period: its renewal is for the new plan and cycle, and the business
 *   moves to it then (SubscriptionCycle, Phase B2's move down). Undone until
 *   then. It and a cancellation replace each other.
 *
 * To Billing Starter is cancelling (SubscriptionService::cancel).
 */
class SubscriptionChanges
{
    public const UP   = 'upgrade';

    public const DOWN = 'downgrade';

    public function __construct(
        private SubscriptionBilling $billing,
        private SubscriptionCycle $cycle,
        private WorkspaceSubscriptionRepository $subscriptions,
        private SubscriptionInvoiceRepository $invoices,
        private BusinessCalendar $calendar,
    ) {}

    /**
     * What moving to $to, $cycle, would cost and when it would happen - the
     * tax on it as it would be today (Phase D1).
     *
     * @return array{kind: string, package: Package, cycle: string, currency: string, amount: string, credit: string, tax: array{name: ?string, rate: ?string, amount: string, note: ?string}, total: string, effective_on: ?string}
     */
    public function preview(Workspace $workspace, Package $to, string $cycle): array
    {
        $subscription = $this->changeable($workspace);
        $kind         = $this->kind($subscription, $to, $cycle);
        $amount       = $this->billing->price($to, $cycle)
            ?? throw ValidationException::withMessages(['cycle' => "{$to->name}'s ".($cycle === WorkspaceSubscription::YEARLY ? 'yearly' : 'monthly').' price has not been announced yet.']);
        $credit       = $kind === self::UP ? $this->credit($workspace, $subscription, $to) : '0.00';

        // No more credit than the new price - nothing is paid back.
        $credit       = bccomp($credit, $amount, 2) > 0 ? $amount : $credit;

        return [
            'kind'         => $kind,
            'package'      => $to,
            'cycle'        => $cycle,
            ...$this->billing->quote($workspace, $to, $cycle, $credit),
            'effective_on' => $kind === self::DOWN ? $subscription->period_ends_on->copy()->addDay()->toDateString() : null,
        ];
    }

    /** Moves the business up - its invoice issued - or down at the period's end; answers what happens, as a sentence. */
    public function apply(Workspace $workspace, Package $to, string $cycle): string
    {
        $change  = $this->preview($workspace, $to, $cycle);
        $current = $this->subscriptions->for($workspace)->package->name;
        $open    = $this->invoices->openFor($workspace);

        if ($change['kind'] === self::UP) {
            if ($open?->kind === SubscriptionInvoice::UPGRADE) {
                $this->refuse('An upgrade is waiting for its payment already: pay it, or withdraw it to choose another.');
            }

            $invoice = DB::transaction(function () use ($workspace, $to, $cycle, $change, $open) {
                // The new plan starts a period of its own: a renewal of this one is not needed.
                if ($open?->kind === SubscriptionInvoice::RENEWAL) {
                    $this->billing->void($open);
                }

                return $this->billing->issue($workspace, $to, $cycle, null, null, SubscriptionInvoice::UPGRADE, $change['credit']);
            });

            ActivityLogService::record('updated', "Asked to upgrade from {$current} to {$to->name}, {$cycle}; invoice {$invoice->number} issued", $workspace, ['source' => 'api']);

            return "Invoice {$invoice->number} is issued for {$invoice->currency} {$invoice->total}. {$to->name} starts as soon as izSuite confirms your payment.";
        }

        DB::transaction(function () use ($workspace, $to, $cycle, $open) {
            $this->subscriptions->save($workspace, ['scheduled_package_id' => $to->id, 'scheduled_cycle' => $cycle, 'cancels_at_period_end' => false]);

            // A renewal issued for the current plan gives way to one for the new.
            if ($open?->kind === SubscriptionInvoice::RENEWAL) {
                $this->billing->void($open);
            }

            $this->cycle->renewIfDue($this->subscriptions->for($workspace));
        });

        $on      = CarbonImmutable::parse($change['effective_on'])->format('j M Y');

        ActivityLogService::record('updated', "Scheduled a move from {$current} to {$to->name}, {$cycle}, on {$on}", $workspace, ['source' => 'api']);

        return "Your plan moves to {$to->name}, {$cycle}, on {$on}, when this paid period ends. Until then you keep {$current}.";
    }

    /** The move waiting for the period's end undone: the renewal is for the current plan again. */
    public function undo(Workspace $workspace): string
    {
        $subscription = $this->subscriptions->for($workspace);

        if ($subscription?->status !== WorkspaceSubscription::ACTIVE || ! $subscription->scheduled_package_id) {
            $this->refuse('There is no plan change waiting to undo.');
        }

        $to           = $subscription->scheduledPackage->name;

        DB::transaction(function () use ($workspace) {
            $this->subscriptions->save($workspace, ['scheduled_package_id' => null, 'scheduled_cycle' => null]);

            if (($open = $this->invoices->openFor($workspace)) && $open->kind === SubscriptionInvoice::RENEWAL) {
                $this->billing->void($open);
            }

            $this->cycle->renewIfDue($this->subscriptions->for($workspace));
        });

        ActivityLogService::record('updated', "Kept {$subscription->package->name} instead of moving to {$to}", $workspace, ['source' => 'api']);

        return "You stay on {$subscription->package->name}, {$subscription->cycle}.";
    }

    /** Only an active subscription changes plan. */
    private function changeable(Workspace $workspace): WorkspaceSubscription
    {
        $subscription = $this->subscriptions->for($workspace);

        if ($subscription?->status !== WorkspaceSubscription::ACTIVE) {
            $this->refuse(match ($subscription?->status) {
                WorkspaceSubscription::TRIALING         => 'Your trial is not paid yet: plans change once it is.',
                WorkspaceSubscription::AWAITING_PAYMENT => 'Your plan has not started yet: withdraw its invoice to choose another.',
                WorkspaceSubscription::GRACE            => 'Pay the renewal first: plans change once the subscription is active again.',
                default                                 => 'Choose a plan first.',
            });
        }

        return $subscription;
    }

    /** Up or down - a higher plan, or monthly to yearly, is up. */
    private function kind(WorkspaceSubscription $subscription, Package $to, string $cycle): string
    {
        $from = $subscription->package;

        if ($to->isFree()) {
            throw ValidationException::withMessages(['package' => 'To move to Billing Starter, cancel your subscription: it ends with its paid period.']);
        }

        if ($to->id === $from->id && $cycle === $subscription->cycle) {
            throw ValidationException::withMessages(['package' => "Your business is on {$to->name}, {$cycle}, already."]);
        }

        $up   = $to->rank > $from->rank || ($to->rank === $from->rank && $cycle === WorkspaceSubscription::YEARLY);

        if ($up && $subscription->cycle === WorkspaceSubscription::YEARLY && $cycle === WorkspaceSubscription::MONTHLY) {
            throw ValidationException::withMessages(['cycle' => 'Choose the yearly price: the months left on your yearly plan count toward it.']);
        }

        return $up ? self::UP : self::DOWN;
    }

    /**
     * What the unused part of the paid period is worth, counted by the day
     * from today: what the period was paid before tax, times the days left
     * over the days it has - rounded down to the cent. Nothing across
     * currencies. The new invoice's tax is on its price less this.
     */
    private function credit(Workspace $workspace, WorkspaceSubscription $subscription, Package $to): string
    {
        $start  = $subscription->period_starts_on->toImmutable();
        $end    = $subscription->period_ends_on->toImmutable();
        $paid   = $this->invoices->paidFor($workspace, $start->toDateString());

        if (! $paid || $paid->currency !== $to->price_currency) {
            return '0.00';
        }

        $today  = CarbonImmutable::parse($this->calendar->today($workspace)->toDateString());
        $days   = (int) round($start->diffInDays($end)) + 1;
        $unused = max(0, min($days, (int) round($today->diffInDays($end, false)) + 1));
        $cents  = intdiv((int) round((float) $paid->net() * 100) * $unused, $days);

        return number_format($cents / 100, 2, '.', '');
    }

    private function refuse(string $message): never
    {
        throw new HttpResponseException(response()->json(['message' => $message], 409));
    }
}
