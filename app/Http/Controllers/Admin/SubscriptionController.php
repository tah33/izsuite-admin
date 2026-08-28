<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreSubscriptionRequest;
use App\Models\Billing\Plan;
use App\Models\User\User;
use App\Repositories\Billing\InvoiceRepository;
use App\Repositories\Billing\SubscriptionRepository;
use App\Services\Shared\ActivityLogService;
use App\Services\Invoices\InvoicePdfService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;

class SubscriptionController extends Controller
{
    public function __construct(
        protected SubscriptionRepository $subscriptionRepository,
        protected InvoiceRepository $invoiceRepository,
        protected InvoicePdfService $invoicePdfService,
    ) {}

    public function index(Request $request)
    {
        try {
            $filters       = [
                'search'        => $request->get('search'),
                'status'        => $request->get('status'),
                'billing_cycle' => $request->get('billing_cycle'),
                'date_from'     => $request->get('date_from'),
                'date_to'       => $request->get('date_to'),
            ];
            $subscriptions = $this->subscriptionRepository->getPaginatedForAdmin($filters);
            $statuses      = $this->subscriptionRepository->getDistinctStatuses();
            $billingCycles = $this->subscriptionRepository->getDistinctBillingCycles();

            return view('admin.subscriptions.index', compact(
                'subscriptions',
                'statuses',
                'billingCycles',
            ));

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function create()
    {
        try {
            $users = User::query()
                ->select(['id', 'name', 'email'])
                ->whereHas('role', fn ($query) => $query->whereNotIn('slug', ['super-admin', 'admin']))
                ->orderBy('name')
                ->get();

            $plans = Plan::query()
                ->where('is_active', true)
                ->ordered()
                ->get();

            return view('admin.subscriptions.create', compact('users', 'plans'));

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function store(StoreSubscriptionRequest $request): RedirectResponse
    {
        try {
            $validated = $request->validated();

            $user      = User::with('role')->findOrFail((int) $validated['recruiter_id']);

            $plan      = Plan::query()
                ->where('is_active', true)
                ->findOrFail((int) $validated['plan_id']);

            $status    = $validated['status'] ?? 'active';

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
            $amount       = $validated['amount'] ?? 0;
            $currency     = strtoupper((string) setting('default_currency', 'USD'));

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

            return redirect()
                ->route('admin.subscriptions.index')
                ->with('success', 'User subscribed to plan successfully.');

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function pause(int $id): RedirectResponse
    {
        try {
            $subscription = $this->subscriptionRepository->findRecruiterById($id);
            abort_unless($subscription, 404);

            if ($subscription->status !== 'active') {
                return redirect()
                    ->route('admin.subscriptions.index')
                    ->with('error', 'Only active subscriptions can be paused.');
            }

            $this->subscriptionRepository->update($subscription, [
                'status' => 'paused',
            ]);

            ActivityLogService::record(
                'updated',
                "Paused subscription \"{$subscription->name}\" for recruiter \"{$subscription->user?->name}\"",
                $subscription
            );

            return redirect()
                ->route('admin.subscriptions.index')
                ->with('success', 'Subscription paused successfully.');

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function resume(int $id): RedirectResponse
    {
        try {
            $subscription = $this->subscriptionRepository->findRecruiterById($id);
            abort_unless($subscription, 404);

            if ($subscription->status !== 'paused') {
                return redirect()
                    ->route('admin.subscriptions.index')
                    ->with('error', 'Only paused subscriptions can be resumed.');
            }

            $this->subscriptionRepository->update($subscription, [
                'status' => 'active',
            ]);

            ActivityLogService::record(
                'updated',
                "Resumed subscription \"{$subscription->name}\" for recruiter \"{$subscription->user?->name}\"",
                $subscription
            );

            return redirect()
                ->route('admin.subscriptions.index')
                ->with('success', 'Subscription resumed successfully.');

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function downloadInvoice(int $id): Response|RedirectResponse
    {
        try {
            $subscription = $this->subscriptionRepository->findRecruiterById($id);
            abort_unless($subscription, 404);

            $invoice      = $this->invoiceRepository->latestForSubscription($subscription->id);

            if (! $invoice) {
                return redirect()
                    ->route('admin.subscriptions.index')
                    ->with('error', 'No invoice found for this subscription.');
            }

            return $this->invoicePdfService->download($invoice);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
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
