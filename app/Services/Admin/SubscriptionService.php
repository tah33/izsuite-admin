<?php

namespace App\Services\Admin;

use App\Models\Billing\Invoice;
use App\Models\Billing\Subscription;
use App\Repositories\Admin\PlanRepository;
use App\Repositories\Billing\InvoiceRepository;
use App\Repositories\Billing\SubscriptionRepository;
use App\Repositories\User\UserRepository;
use App\Services\Shared\ActivityLogService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;

/**
 * Subscription History in the admin panel: the subscriptions, subscribing a
 * user to a plan by hand, pausing and resuming one, and its latest invoice.
 */
class SubscriptionService
{
    public function __construct(
        protected SubscriptionRepository $subscriptionRepository,
        protected InvoiceRepository $invoiceRepository,
        protected UserRepository $userRepository,
        protected PlanRepository $planRepository,
    ) {}

    /** A page of subscriptions, and what the filters offer. */
    public function history(array $filters): array
    {
        return [
            'subscriptions' => $this->subscriptionRepository->getPaginatedForAdmin($filters),
            'statuses'      => $this->subscriptionRepository->getDistinctStatuses(),
            'billingCycles' => $this->subscriptionRepository->getDistinctBillingCycles(),
        ];
    }

    /** Who can be subscribed, and to which plans. */
    public function createFormData(): array
    {
        return [
            'users' => $this->userRepository->nonAdmins(),
            'plans' => $this->planRepository->getActive(),
        ];
    }

    /**
     * Subscribes the user to the plan in $validated (StoreSubscriptionRequest),
     * cancelling the plan they were on.
     */
    public function subscribe(array $validated): Subscription
    {
        $user = $this->userRepository->findWithRoleOrFail((int) $validated['recruiter_id']);
        $plan = $this->planRepository->findActiveOrFail((int) $validated['plan_id']);

        $status = $validated['status'] ?? 'active';

        $this->subscriptionRepository->cancelActivePlanSubscriptionsForUser($user->id);

        $startDate = isset($validated['start_date']) ? Carbon::parse($validated['start_date']) : now();

        if (isset($validated['next_renewal_date'])) {
            $nextRenewalDate = Carbon::parse($validated['next_renewal_date']);
        } elseif ($status === 'cancelled') {
            $nextRenewalDate = null;
        } else {
            $nextRenewalDate = $this->nextRenewalDate($startDate, $plan->billing_type ?? 'monthly');
        }

        // Blank amount means free access to the selected plan.
        $amount   = $validated['amount'] ?? 0;
        $currency = strtoupper((string) setting('default_currency', 'USD'));

        $subscription = $this->subscriptionRepository->create([
            'user_id'             => $user->id,
            'plan_id'             => $plan->id,
            'plan_slug'           => $plan->slug,
            'name'                => $plan->name,
            'description'         => $plan->description,
            'amount'              => $amount,
            'currency'            => $currency,
            'payment_method_slug' => null,
            'billing_cycle'       => $plan->billing_type ?? 'monthly',
            'start_date'          => $startDate,
            'next_renewal_date'   => $nextRenewalDate,
            'status'              => $status,
            'cancelled_at'        => $status === 'cancelled' ? now() : null,
            'job_postings_limit'  => $plan->job_postings_limit,
            'job_postings_used'   => 0,
            'ai_screenings_limit' => $plan->ai_screenings_limit,
            'ai_screenings_used'  => 0,
            'team_members_limit'  => $plan->team_members_limit,
            'team_members_used'   => 0,
            'is_manual'           => false,
        ]);

        ActivityLogService::record(
            'created',
            "Subscribed user \"{$user->name}\" to plan \"{$plan->name}\"",
            $subscription
        );

        return $subscription;
    }

    public function find(int $id): ?Subscription
    {
        return $this->subscriptionRepository->findRecruiterById($id);
    }

    /** Why $subscription cannot be paused, if it cannot. */
    public function pauseProblem(Subscription $subscription): ?string
    {
        return $subscription->status !== 'active' ? 'Only active subscriptions can be paused.' : null;
    }

    /** Why $subscription cannot be resumed, if it cannot. */
    public function resumeProblem(Subscription $subscription): ?string
    {
        return $subscription->status !== 'paused' ? 'Only paused subscriptions can be resumed.' : null;
    }

    public function pause(Subscription $subscription): void
    {
        $this->subscriptionRepository->update($subscription, [
            'status' => 'paused',
        ]);

        ActivityLogService::record(
            'updated',
            "Paused subscription \"{$subscription->name}\" for recruiter \"{$subscription->user?->name}\"",
            $subscription
        );
    }

    public function resume(Subscription $subscription): void
    {
        $this->subscriptionRepository->update($subscription, [
            'status' => 'active',
        ]);

        ActivityLogService::record(
            'updated',
            "Resumed subscription \"{$subscription->name}\" for recruiter \"{$subscription->user?->name}\"",
            $subscription
        );
    }

    public function latestInvoice(Subscription $subscription): ?Invoice
    {
        return $this->invoiceRepository->latestForSubscription($subscription->id);
    }

    private function nextRenewalDate(Carbon $startDate, string $billingCycle): ?Carbon
    {
        return match ($billingCycle) {
            'yearly'    => $startDate->copy()->addYear(),
            'quarterly' => $startDate->copy()->addMonths(3),
            'weekly'    => $startDate->copy()->addWeek(),
            default     => $startDate->copy()->addMonth(),
        };
    }
}
