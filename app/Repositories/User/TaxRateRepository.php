<?php

namespace App\Repositories\User;

use App\Enums\TaxTreatment;
use App\Models\User\Item;
use App\Models\User\TaxRate;
use App\Models\User\Workspace;
use Illuminate\Database\Eloquent\Collection;

/**
 * Tax rates belong to one business (workspace). Every lookup is scoped to it,
 * so another business's rate - the same owner's or anyone else's - is simply
 * not found.
 */
class TaxRateRepository
{
    /** All of them, active ones first, then by name: a business has a handful, and the list shows every one. */
    public function getFor(Workspace $workspace): Collection
    {
        return $workspace->taxRates()->orderByDesc('is_active')->orderBy('name')->orderBy('id')->get();
    }

    public function findFor(Workspace $workspace, int $id): ?TaxRate
    {
        return $workspace->taxRates()->find($id);
    }

    /** The rates among $ids of the business. */
    public function findManyFor(Workspace $workspace, array $ids): Collection
    {
        return $workspace->taxRates()->whereIn('id', $ids)->get();
    }

    /**
     * The business's first active sales rate of $treatment in effect on $date
     * - what a customer with that tax treatment is charged, an exempt one
     * included (Doc 08 section 17, Doc 11 section 11).
     */
    public function forSalesWithTreatment(Workspace $workspace, TaxTreatment $treatment, string $date): ?TaxRate
    {
        return $this->withTreatment($workspace, TaxRate::SALES, $treatment, $date);
    }

    /** The same for purchases: what a supplier with that tax treatment charges (Doc 09 section 14). */
    public function forPurchasesWithTreatment(Workspace $workspace, TaxTreatment $treatment, string $date): ?TaxRate
    {
        return $this->withTreatment($workspace, TaxRate::PURCHASES, $treatment, $date);
    }

    private function withTreatment(Workspace $workspace, string $side, TaxTreatment $treatment, string $date): ?TaxRate
    {
        return $workspace->taxRates()
            ->where('treatment', $treatment->value)
            ->where("for_{$side}", true)
            ->where('is_active', true)
            ->where('effective_from', '<=', $date)
            ->where(fn ($query) => $query->whereNull('effective_to')->orWhere('effective_to', '>=', $date))
            ->orderBy('id')
            ->first();
    }

    /** By its code, as an import names it - codes are stored upper case. */
    public function findByCodeFor(Workspace $workspace, string $code): ?TaxRate
    {
        return $workspace->taxRates()->where('code', mb_strtoupper(trim($code)))->first();
    }

    /** The business's default tax for $side - 'sales' or 'purchases' - if it has one. */
    public function defaultFor(Workspace $workspace, string $side): ?TaxRate
    {
        return $workspace->taxRates()->where("default_for_{$side}", true)->first();
    }

    /** Makes $rate the business's only default for $side, or - null - leaves it none. */
    public function setDefault(Workspace $workspace, string $side, ?TaxRate $rate): void
    {
        $workspace->taxRates()
            ->where("default_for_{$side}", true)
            ->when($rate, fn ($query) => $query->whereKeyNot($rate->id))
            ->update(["default_for_{$side}" => false]);

        if ($rate) {
            $workspace->taxRates()->whereKey($rate->id)->update(["default_for_{$side}" => true]);
        }
    }

    /** How many items start their sales or purchases with it. */
    public function itemCount(TaxRate $rate): int
    {
        return Item::query()
            ->where(fn ($query) => $query->where('sales_tax_id', $rate->id)->orWhere('purchase_tax_id', $rate->id))
            ->count();
    }

    /** Made through the business, so its workspace is never read from $data. */
    public function create(Workspace $workspace, array $data): TaxRate
    {
        return $workspace->taxRates()->create($data);
    }

    public function update(TaxRate $rate, array $data): TaxRate
    {
        $rate->update($data);

        return $rate;
    }

    public function delete(TaxRate $rate): void
    {
        $rate->delete();
    }
}
