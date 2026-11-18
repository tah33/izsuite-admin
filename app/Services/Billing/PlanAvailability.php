<?php

namespace App\Services\Billing;

use App\Models\Billing\Package;
use App\Models\User\Workspace;
use App\Repositories\User\WorkspaceBillingRepository;
use Illuminate\Validation\ValidationException;

/**
 * Where a plan is offered (Doc 15 section 15.8 - Phase E3a): on the cycles
 * izSuite offers it on, and in the billing countries it names (none named,
 * all). Subscribing and moving plan both ask; the plans compared leave out
 * those not offered where the business is billed.
 */
class PlanAvailability
{
    public function __construct(
        private WorkspaceBillingRepository $billingDetails,
    ) {}

    /** Refuses $package on $cycle for $workspace where it is not offered - its country only once its billing details give one. */
    public function ensure(Package $package, string $cycle, Workspace $workspace): void
    {
        if (! $package->offersCycle($cycle)) {
            throw ValidationException::withMessages(['cycle' => "{$package->name} is not offered {$cycle}."]);
        }

        $country = $this->countryOf($workspace);

        if ($country !== null && ! $package->offeredIn($country)) {
            throw ValidationException::withMessages(['package' => "{$package->name} is not offered where your business is billed."]);
        }
    }

    /** $workspace's billing country, from its billing details; null before it has them. */
    public function countryOf(Workspace $workspace): ?string
    {
        return $this->billingDetails->for($workspace)?->country;
    }
}
