<?php

namespace App\Services\Billing;

use App\Models\Billing\Package;
use App\Models\Billing\Promotion;
use App\Models\User\Workspace;
use App\Repositories\Billing\PromotionRepository;
use App\Repositories\Billing\SubscriptionInvoiceRepository;
use App\Repositories\User\WorkspaceBillingRepository;
use App\Services\Shared\BusinessCalendar;
use App\Support\Countries;
use Illuminate\Validation\ValidationException;

/**
 * Promotion codes as a business uses them (Doc 15 section 15.17 - Phase
 * C3b): typed when subscribing, a code takes its percent or fixed amount
 * off the new subscription's first invoice, before credits and tax. It
 * holds only for the plans, cycles and billing countries it names, between
 * its days, up to its total and per-business uses - and, if so set, for a
 * business that has never paid izSuite. Refused, the business is told why.
 */
class Promotions
{
    public function __construct(
        private PromotionRepository $promotions,
        private SubscriptionInvoiceRepository $invoices,
        private WorkspaceBillingRepository $billingDetails,
        private BusinessCalendar $calendar,
    ) {}

    /**
     * The promotion $code names for $workspace subscribing to $package's
     * $cycle, invoiced in $currency - refused, on the code field, with why
     * it takes nothing off. $lock holds it while the invoice takes it, so
     * its last use cannot go twice.
     */
    public function forSubscribing(string $code, Workspace $workspace, Package $package, string $cycle, string $currency, bool $lock = false): Promotion
    {
        $promotion = $this->promotions->findByCode(strtoupper(trim($code)), $lock)
            ?? throw ValidationException::withMessages(['code' => 'There is no promotion with this code.']);
        $day       = $this->calendar->today($workspace);
        $country   = $this->billingDetails->for($workspace)?->country;

        $why       = match (true) {
            $promotion->status === Promotion::PAUSED                                     => 'This code is not in use right now.',
            $promotion->starts_on !== null && $promotion->starts_on->gt($day)            => 'This code can be used from '.$promotion->starts_on->format('j M Y').'.',
            $promotion->ends_on !== null && $promotion->ends_on->lt($day)                => 'This code has ended.',
            ! empty($promotion->packages) && ! in_array($package->key, $promotion->packages, true) => "This code is not for {$package->name}.",
            ! empty($promotion->cycles) && ! in_array($cycle, $promotion->cycles, true)  => 'This code is for '.implode(' and ', $promotion->cycles).' plans only.',
            ! empty($promotion->countries) && ! in_array($country, $promotion->countries, true) => 'This code is not for businesses billed in '.(Countries::name($country) ?? 'your country').'.',
            $promotion->discount_type === Promotion::FIXED && $promotion->currency !== $currency => "This code is for prices in {$promotion->currency}.",
            $promotion->max_redemptions !== null && $this->promotions->uses($promotion) >= $promotion->max_redemptions => 'This code has been used up.',
            $this->promotions->uses($promotion, $workspace) >= $promotion->per_business  => 'Your business has used this code already.',
            $promotion->new_customers_only && $this->invoices->hasPaid($workspace)       => 'This code is for new customers.',
            default                                                                      => null,
        };

        if ($why) {
            throw ValidationException::withMessages(['code' => $why]);
        }

        return $promotion;
    }

    /** What $promotion takes off $amount in $currency: its percent, to the nearest cent, or its fixed amount - never more than the price. */
    public function discountOn(Promotion $promotion, string $amount, string $currency): string
    {
        if ($promotion->discount_type === Promotion::PERCENT) {
            // In cents, rounded half up: 12.5% of 19.50 is 2.4375, so 2.44.
            $cents = bcdiv(bcadd(bcmul(bcmul($amount, '100', 0), (string) $promotion->discount_value, 2), '50', 2), '100', 0);
            $off   = bcdiv($cents, '100', 2);
        } else {
            $off   = $promotion->currency === $currency ? bcadd((string) $promotion->discount_value, '0', 2) : '0.00';
        }

        return bccomp($off, $amount, 2) > 0 ? bcadd($amount, '0', 2) : $off;
    }
}
