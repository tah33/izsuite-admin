<?php

namespace Tests\Concerns;

use App\Models\Billing\Package;
use App\Models\Billing\PackagePrice;

/** A plan's prices for a test (Doc 15 Phase C3a): in force from long before, in its main currency unless given. */
trait PricesPlans
{
    protected function priced(string $key, ?string $monthly, ?string $yearly = null, ?string $currency = null, string $from = '2000-01-01'): void
    {
        $package = Package::where('key', $key)->firstOrFail();

        foreach (['monthly' => $monthly, 'yearly' => $yearly] as $cycle => $amount) {
            if ($amount !== null) {
                PackagePrice::create(['package_id' => $package->id, 'currency' => $currency ?? $package->price_currency ?? 'USD', 'cycle' => $cycle, 'amount' => $amount, 'starts_on' => $from]);
            }
        }
    }
}
