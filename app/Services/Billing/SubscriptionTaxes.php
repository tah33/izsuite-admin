<?php

namespace App\Services\Billing;

use App\Models\Billing\SubscriptionTax;
use App\Models\User\Workspace;
use App\Repositories\Billing\SubscriptionTaxRepository;
use App\Repositories\User\WorkspaceBillingRepository;
use Carbon\CarbonInterface;

/**
 * The tax on a subscription invoice (Doc 13 section 16 - Phase D1): izSuite's
 * rule for the billing details' country, the one in force on the day, on the
 * amount after any upgrade credit - prices are before tax - to the cent, half
 * up. A rule with a reverse charge charges a buyer that gives its tax ID
 * none, and prints its note instead. A country without a rule pays none.
 */
class SubscriptionTaxes
{
    private const NONE = ['name' => null, 'rate' => null, 'amount' => '0.00', 'note' => null];

    public function __construct(
        private SubscriptionTaxRepository $taxes,
        private WorkspaceBillingRepository $billingDetails,
    ) {}

    /** @return array{name: ?string, rate: ?string, amount: string, note: ?string} the tax on $net for $workspace, on $day */
    public function for(Workspace $workspace, string $net, CarbonInterface $day): array
    {
        $details = $this->billingDetails->for($workspace);
        $rule    = $details?->country ? $this->taxes->effective($details->country, $day->toDateString()) : null;

        if (! $rule) {
            return self::NONE;
        }

        if ($rule->reverse_charge && filled($details->tax_id)) {
            return ['name' => $rule->name, 'rate' => '0.000', 'amount' => '0.00', 'note' => $rule->reverse_charge_note ?: SubscriptionTax::REVERSE_CHARGE_NOTE];
        }

        return ['name' => $rule->name, 'rate' => (string) $rule->rate, 'amount' => self::percentOf($net, (string) $rule->rate), 'note' => null];
    }

    /** $rate percent of $amount, to the cent, half up: 15% of 19.50 is 2.93. */
    public static function percentOf(string $amount, string $rate): string
    {
        return bcdiv(bcadd(bcmul($amount, $rate, 6), '0.5', 6), '100', 2);
    }
}
