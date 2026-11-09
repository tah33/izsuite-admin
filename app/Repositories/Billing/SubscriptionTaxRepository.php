<?php

namespace App\Repositories\Billing;

use App\Models\Billing\SubscriptionTax;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class SubscriptionTaxRepository
{
    /** The rules by country, each country's latest first; $countries narrows them (a search by name). */
    public function paginate(?array $countries, int $perPage): LengthAwarePaginator
    {
        return SubscriptionTax::query()
            ->when($countries !== null, fn ($query) => $query->whereIn('country', $countries))
            ->orderBy('country')
            ->orderByDesc('starts_on')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function find(int $id): ?SubscriptionTax
    {
        return SubscriptionTax::find($id);
    }

    /** $country's rule on $date: the one that started last, on or before it. */
    public function effective(string $country, string $date): ?SubscriptionTax
    {
        return SubscriptionTax::where('country', $country)
            ->whereDate('starts_on', '<=', $date)
            ->orderByDesc('starts_on')
            ->first();
    }

    /**
     * Of $countries, the rule each has in force on $date - the one that
     * started last by then.
     *
     * @return list<int>
     */
    public function inForceIds(array $countries, string $date): array
    {
        return SubscriptionTax::whereIn('country', $countries)
            ->whereDate('starts_on', '<=', $date)
            ->orderByDesc('starts_on')
            ->get(['id', 'country'])
            ->unique('country')
            ->pluck('id')
            ->all();
    }

    /** Whether $country has a rule starting on $date already, other than $exceptId. */
    public function startsOnTaken(string $country, string $date, ?int $exceptId): bool
    {
        return SubscriptionTax::where('country', $country)
            ->whereDate('starts_on', $date)
            ->when($exceptId, fn ($query) => $query->whereKeyNot($exceptId))
            ->exists();
    }

    public function create(array $data): SubscriptionTax
    {
        return SubscriptionTax::create($data);
    }

    public function update(SubscriptionTax $tax, array $data): SubscriptionTax
    {
        $tax->update($data);

        return $tax;
    }

    public function delete(SubscriptionTax $tax): void
    {
        $tax->delete();
    }
}
