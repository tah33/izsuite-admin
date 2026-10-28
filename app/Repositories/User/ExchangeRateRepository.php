<?php

namespace App\Repositories\User;

use App\Models\User\ExchangeRate;
use App\Models\User\Workspace;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Arr;

/**
 * Exchange rates belong to one business (workspace), and count only against
 * its current base currency. Every lookup is scoped to both.
 */
class ExchangeRateRepository
{
    /** Every rate of $currencies: by currency, newest first. */
    public function getFor(Workspace $workspace, array $currencies): Collection
    {
        return $this->againstBase($workspace)
            ->whereIn('currency', $currencies)
            ->orderBy('currency')
            ->orderByDesc('effective_date')
            ->get();
    }

    public function findFor(Workspace $workspace, int $id): ?ExchangeRate
    {
        return $workspace->exchangeRates()->find($id);
    }

    /** The rate in force for $currency on $date ("2026-10-28"): the latest from that day or before. */
    public function rateOn(Workspace $workspace, string $currency, string $date): ?ExchangeRate
    {
        return $this->againstBase($workspace)
            ->where('currency', $currency)
            ->where('effective_date', '<=', $date)
            ->orderByDesc('effective_date')
            ->first();
    }

    /** One rate per currency and day: a second for the same day replaces the first. */
    public function put(Workspace $workspace, array $data): ExchangeRate
    {
        return $workspace->exchangeRates()->updateOrCreate(
            Arr::only($data, ['base_currency', 'currency', 'effective_date']),
            Arr::only($data, ['rate', 'created_by']),
        );
    }

    public function delete(ExchangeRate $rate): void
    {
        $rate->delete();
    }

    private function againstBase(Workspace $workspace): HasMany
    {
        return $workspace->exchangeRates()->where('base_currency', $workspace->base_currency);
    }
}
