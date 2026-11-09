<?php

namespace App\Services\Api\Billing;

use App\Models\Billing\SubscriptionInvoice;
use App\Models\Billing\WorkspaceSubscription;
use App\Models\User\User;
use App\Models\User\Workspace;
use App\Models\User\WorkspaceBilling;
use App\Repositories\Billing\SubscriptionInvoiceRepository;
use App\Repositories\Billing\WorkspaceSubscriptionRepository;
use App\Repositories\User\WorkspaceBillingRepository;
use App\Services\Api\Apps\PublicCatalog;
use App\Services\Shared\ActivityLogService;
use App\Services\User\Workspaces\PlanLimits;
use App\Support\SettingChanges;

/**
 * Settings > Apps & Subscription's billing side (Doc 13 section 11), for
 * the owner and roles with Manage Subscription: the business's plan and its
 * status, the plans to compare it with, and the billing details its izSuite
 * invoices go to.
 *
 * The status is its subscription's - on a trial, awaiting its first
 * payment, or active for a paid period (Phase C1, SubscriptionService) -
 * else Free on Billing Starter, or Active on a plan izSuite set it up with
 * in the admin. An invoice still to be paid comes with it, and whether the
 * business's one trial is still to be had.
 *
 * A business over its plan's user or location limit - moved to a lower plan
 * - says so, and whether $user is the owner who chooses what stays active
 * (Doc 13 section 14, OverLimitService).
 */
class BillingService
{
    public function __construct(
        protected WorkspaceBillingRepository $billingRepository,
        protected WorkspaceSubscriptionRepository $subscriptions,
        protected SubscriptionInvoiceRepository $invoices,
        protected PublicCatalog $catalog,
        protected PlanLimits $planLimits,
    ) {}

    /**
     * @return array{package: array|null, status: string, subscription: ?WorkspaceSubscription, open_invoice: ?SubscriptionInvoice,
     *               trial_available: bool, packages: list<array>, details: ?WorkspaceBilling, excess: ?array}
     */
    public function overview(Workspace $workspace, User $user): array
    {
        $current      = $workspace->package;
        $subscription = $this->subscriptions->for($workspace);
        $subscribed   = $subscription?->isCurrent() ? $subscription : null;

        return [
            'package'         => $current ? ['key' => $current->key, 'name' => $current->name, 'removes_branding' => $current->removes_branding] : null,
            'status'          => $subscribed?->status ?? ($current === null || $current->isFree() ? 'free' : 'active'),
            'subscription'    => $subscribed,
            'open_invoice'    => $this->invoices->openFor($workspace),
            'trial_available' => ! $subscription?->trial_starts_on,
            'packages'        => array_map(fn (array $package) => [...$package, 'current' => $package['key'] === $current?->key], $this->catalog->packages()),
            'details'         => $this->billingRepository->for($workspace),
            'excess'          => $this->excess($workspace, $user),
        ];
    }

    /** @return array{users: ?array, locations: ?array, can_choose: bool}|null the limits passed, each used of its limit - null within all */
    private function excess(Workspace $workspace, User $user): ?array
    {
        $usage     = $this->planLimits->usage($workspace);
        $users     = $usage['users']['state'] === 'over' ? $usage['users'] : null;
        $locations = $usage['locations']['state'] === 'over' ? $usage['locations'] : null;

        return $users || $locations ? ['users' => $users, 'locations' => $locations, 'can_choose' => $workspace->isOwnedBy($user)] : null;
    }

    /** Saves the billing details (Doc 13 sections 11 and 21), recording what changed. */
    public function saveDetails(Workspace $workspace, array $data): WorkspaceBilling
    {
        $before  = $this->billingRepository->for($workspace)?->only(array_keys($data)) ?? [];
        $details = $this->billingRepository->save($workspace, $data);

        ActivityLogService::record('updated', 'Changed the billing details', $workspace, [
            'source'  => 'api',
            'changes' => SettingChanges::between($before, $details->only(array_keys($data))),
        ]);

        return $details;
    }
}
