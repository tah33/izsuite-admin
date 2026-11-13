<?php

namespace App\Repositories\Billing;

use App\Models\Billing\Package;
use App\Models\Billing\PackagePrice;
use Illuminate\Database\Eloquent\Collection;

/** A plan's prices in each currency and cycle, by their days (Doc 15 Phase C3a). */
class PackagePriceRepository
{
    /** The price of $package in $currency for $cycle in force on $day - null when there is none. */
    public function inForce(Package $package, string $currency, string $cycle, string $day): ?PackagePrice
    {
        return $package->prices()
            ->where('currency', $currency)
            ->where('cycle', $cycle)
            ->whereDate('starts_on', '<=', $day)
            ->where(fn ($query) => $query->whereNull('ends_on')->orWhereDate('ends_on', '>=', $day))
            ->latest('starts_on')
            ->first();
    }

    /** Whether $package has any price in $currency in force on $day. */
    public function hasCurrency(Package $package, string $currency, string $day): bool
    {
        return $package->prices()
            ->where('currency', $currency)
            ->whereDate('starts_on', '<=', $day)
            ->where(fn ($query) => $query->whereNull('ends_on')->orWhereDate('ends_on', '>=', $day))
            ->exists();
    }

    /** @return Collection<int, PackagePrice> every price of $package, by currency and cycle, the latest first - its history */
    public function history(Package $package): Collection
    {
        return $package->prices()->with('creator:id,first_name,last_name')->orderBy('currency')->orderBy('cycle')->latest('starts_on')->get();
    }

    /** The price of $currency and $cycle that starts on or after $day - one waiting to start, or the same day. */
    public function startingFrom(Package $package, string $currency, string $cycle, string $day): ?PackagePrice
    {
        return $package->prices()->where('currency', $currency)->where('cycle', $cycle)->whereDate('starts_on', '>=', $day)->first();
    }

    /** The open price of $currency and $cycle - the latest, followed by none. */
    public function open(Package $package, string $currency, string $cycle): ?PackagePrice
    {
        return $package->prices()->where('currency', $currency)->where('cycle', $cycle)->whereNull('ends_on')->latest('starts_on')->first();
    }

    /** The price of $currency and $cycle that ends on $day - the one the price starting the day after followed. */
    public function endingOn(Package $package, string $currency, string $cycle, string $day): ?PackagePrice
    {
        return $package->prices()->where('currency', $currency)->where('cycle', $cycle)->whereDate('ends_on', $day)->first();
    }

    public function find(Package $package, int $id): ?PackagePrice
    {
        return $package->prices()->find($id);
    }

    public function create(array $data): PackagePrice
    {
        return PackagePrice::create($data);
    }

    public function update(PackagePrice $price, array $data): PackagePrice
    {
        $price->update($data);

        return $price;
    }

    public function delete(PackagePrice $price): void
    {
        $price->delete();
    }
}
