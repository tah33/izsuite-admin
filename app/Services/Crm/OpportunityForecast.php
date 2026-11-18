<?php

namespace App\Services\Crm;

use App\Models\User\CrmOpportunity;
use App\Models\User\Workspace;
use App\Services\Api\BusinessSetup\ExchangeRateService;
use App\Services\Shared\BusinessCalendar;
use App\Support\Decimal;

/**
 * What opportunities are worth to the forecast (Doc 17 section 17.14 -
 * Phase D1): the expected value times the probability, in the opportunity's
 * own currency, and in the business's base currency at the rate in force
 * today. That is an estimate: it is never money, makes no exchange gain or
 * loss, and a currency without a rate today has no base value.
 */
class OpportunityForecast
{
    public function __construct(
        protected ExchangeRateService $rates,
        protected BusinessCalendar $calendar,
    ) {}

    /**
     * Each of $opportunities' weighted value, and that value in the base
     * currency - null where its currency has no rate today - by id. Each
     * currency's rate is read once.
     *
     * @param  iterable<CrmOpportunity>  $opportunities
     * @return array<int, array{weighted: string, base: string|null}>
     */
    public function of(Workspace $workspace, iterable $opportunities): array
    {
        $today  = $this->calendar->today($workspace)->toDateString();
        $rates  = [];
        $values = [];

        foreach ($opportunities as $opportunity) {
            $rate     = $rates[$opportunity->currency] ??= $this->rates->rateOn($workspace, $opportunity->currency, $today) ?? '';
            $weighted = Decimal::round(bcdiv(bcmul((string) $opportunity->expected_value, (string) $opportunity->probability, 4), '100', 4));

            $values[$opportunity->id] = [
                'weighted' => $weighted,
                'base'     => $rate === '' ? null : Decimal::round(bcmul($weighted, $rate, 8)),
            ];
        }

        return $values;
    }

    /**
     * What $opportunities are worth together in the base currency at today's
     * rates (Phase D4's board columns): their weighted values - or, for
     * $weighted false, their whole values, as a won or lost column counts
     * them - each rounded as the list rounds it. Those of a currency without
     * a rate today are left out and counted.
     *
     * @param  iterable<CrmOpportunity>  $opportunities
     * @return array{total: string, unrated: int}
     */
    public function total(Workspace $workspace, iterable $opportunities, bool $weighted = true): array
    {
        $today   = $this->calendar->today($workspace)->toDateString();
        $rates   = [];
        $total   = '0';
        $unrated = 0;

        foreach ($opportunities as $opportunity) {
            $rate = $rates[$opportunity->currency] ??= $this->rates->rateOn($workspace, $opportunity->currency, $today) ?? '';

            if ($rate === '') {
                $unrated++;

                continue;
            }

            $value = $weighted
                ? Decimal::round(bcdiv(bcmul((string) $opportunity->expected_value, (string) $opportunity->probability, 4), '100', 4))
                : Decimal::round((string) $opportunity->expected_value);
            $total = bcadd($total, Decimal::round(bcmul($value, $rate, 8)), 2);
        }

        return ['total' => Decimal::round($total), 'unrated' => $unrated];
    }

    /** Sets $opportunities' weighted_value and base_weighted_value, for their resources to show. */
    public function attach(Workspace $workspace, iterable $opportunities): void
    {
        $values = $this->of($workspace, $opportunities);

        foreach ($opportunities as $opportunity) {
            $opportunity->setAttribute('weighted_value', $values[$opportunity->id]['weighted']);
            $opportunity->setAttribute('base_weighted_value', $values[$opportunity->id]['base']);
        }
    }
}
