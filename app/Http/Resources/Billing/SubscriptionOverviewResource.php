<?php

namespace App\Http\Resources\Billing;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The business's subscription as Settings shows it (Doc 13 section 11): its
 * plan and status - free, trialing, awaiting_payment, active, grace or
 * cancellation_scheduled - the subscription it is paying for - its cycle,
 * trial, paid period, grace and a renewal paid ahead - and the invoice it
 * has still to pay, whether its one trial is still to be had, the plans to
 * compare it with - its own marked - its billing details, null until first
 * saved, and the limits it is over.
 *
 * @property array{package: array|null, status: string, subscription: mixed, renewed_until: mixed, open_invoice: mixed, trial_available: bool, packages: list<array>, details: mixed, excess: ?array} $resource
 */
class SubscriptionOverviewResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $subscription = $this->resource['subscription'];

        return [
            'package'         => $this->resource['package'] ? [
                'name'             => $this->resource['package']['name'],
                'removes_branding' => $this->resource['package']['removes_branding'],
            ] : null,
            'status'          => $this->resource['status'],
            'subscription'    => $subscription ? [
                'package'          => $subscription->package?->name,
                'cycle'            => $subscription->cycle,
                'trial_ends_on'    => $subscription->trial_ends_on?->toDateString(),
                'period_starts_on' => $subscription->period_starts_on?->toDateString(),
                'period_ends_on'   => $subscription->period_ends_on?->toDateString(),
                // In grace (Phase C2a): its last day; the next, back to Billing Starter.
                'grace_ends_on'    => $subscription->grace_ends_on?->toDateString(),
                // A renewal paid ahead: the plan is paid up to this day.
                'renewed_until'    => $this->resource['renewed_until']?->toDateString(),
                // A move down (Phase C2b), as the paid period ends.
                'scheduled_change' => $subscription->scheduledPackage ? [
                    'package' => $subscription->scheduledPackage->name,
                    'cycle'   => $subscription->scheduled_cycle,
                    'on'      => $subscription->period_ends_on?->copy()->addDay()->toDateString(),
                ] : null,
            ] : null,
            'open_invoice'    => $this->resource['open_invoice'] ? new SubscriptionInvoiceResource($this->resource['open_invoice'], toPay: true) : null,
            'trial_available' => $this->resource['trial_available'],
            'packages'        => PackageOfferResource::collection($this->resource['packages']),
            'details'         => $this->resource['details'] ? new BillingDetailsResource($this->resource['details']) : null,
            // Over a user or location limit (Doc 13 section 14): each passed, used of its limit, and whether this user chooses what stays.
            'excess'          => $this->resource['excess'] ? [
                'users'      => $this->usedOf($this->resource['excess']['users']),
                'locations'  => $this->usedOf($this->resource['excess']['locations']),
                'can_choose' => $this->resource['excess']['can_choose'],
            ] : null,
        ];
    }

    /** @return array{used: int, limit: int}|null */
    private function usedOf(?array $count): ?array
    {
        return $count ? ['used' => $count['used'], 'limit' => $count['limit']] : null;
    }
}
