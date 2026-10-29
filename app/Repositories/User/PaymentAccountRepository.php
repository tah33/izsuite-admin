<?php

namespace App\Repositories\User;

use App\Models\User\Location;
use App\Models\User\PaymentAccount;
use App\Models\User\Workspace;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Payment accounts belong to one business (workspace). Every lookup is scoped
 * to it, so another business's account - the same owner's or anyone else's -
 * is simply not found.
 */
class PaymentAccountRepository
{
    /** All of them, alphabetical, with their locations: a business has a handful, and the list shows every one. */
    public function getFor(Workspace $workspace): Collection
    {
        return $workspace->paymentAccounts()->with('location:id,name')->orderBy('name')->orderBy('id')->get();
    }

    public function findFor(Workspace $workspace, int $id): ?PaymentAccount
    {
        return $workspace->paymentAccounts()->with('location:id,name')->find($id);
    }

    /**
     * The business's other active accounts that could take over $account's
     * $for default (Doc 05 section 8): the same currency and location, and
     * allowed that kind of payment - alphabetical.
     */
    public function defaultCandidates(Workspace $workspace, PaymentAccount $account, string $for): Collection
    {
        return $this->inScope($workspace, $account->currency, $account->location_id)
            ->where('id', '!=', $account->id)
            ->where('is_active', true)
            ->where(PaymentAccount::DEFAULTS[$for], true)
            ->orderBy('name')
            ->get();
    }

    /** Takes the $for default away from every account of the currency and location - before another gets it. */
    public function clearDefault(Workspace $workspace, string $for, string $currency, ?int $locationId): void
    {
        $this->inScope($workspace, $currency, $locationId)->update(["default_{$for}" => false]);
    }

    /**
     * What a document in $currency at location $locationId shows of the
     * business's accounts (Doc 05 section 9): the active ones set to show on
     * documents, of that location or the whole business - of any, for one
     * with no location, like a statement - the default receiving one first,
     * then by name.
     */
    public function forDocuments(Workspace $workspace, string $currency, ?int $locationId): Collection
    {
        return $workspace->paymentAccounts()
            ->where('currency', $currency)
            ->where('is_active', true)
            ->where('show_on_documents', true)
            ->when($locationId, fn ($query) => $query->where(fn ($query) => $query->whereNull('location_id')->orWhere('location_id', $locationId)))
            ->orderByDesc('default_receiving')
            ->orderBy('name')
            ->get();
    }

    public function usesLocation(Location $location): bool
    {
        return PaymentAccount::where('location_id', $location->id)->exists();
    }

    public function existsFor(Workspace $workspace): bool
    {
        return $workspace->paymentAccounts()->exists();
    }

    /** Whether any account starts with money in it (or owed) - which ties the base currency down. */
    public function hasOpeningBalance(Workspace $workspace): bool
    {
        return $workspace->paymentAccounts()->where('opening_balance', '!=', 0)->exists();
    }

    /** The first account, by name, in a currency outside $currencies. */
    public function firstOutside(Workspace $workspace, array $currencies): ?PaymentAccount
    {
        return $workspace->paymentAccounts()->whereNotIn('currency', $currencies)->orderBy('name')->first();
    }

    /** Made through the business, so its workspace is never read from $data - with its location, as the lookups have it. */
    public function create(Workspace $workspace, array $data): PaymentAccount
    {
        return $workspace->paymentAccounts()->create($data)->load('location:id,name');
    }

    public function update(PaymentAccount $account, array $data): PaymentAccount
    {
        $account->update($data);

        return $account;
    }

    public function delete(PaymentAccount $account): void
    {
        $account->delete();
    }

    /** The business's accounts of one currency and one location - or of none. */
    private function inScope(Workspace $workspace, string $currency, ?int $locationId): HasMany
    {
        return $workspace->paymentAccounts()
            ->where('currency', $currency)
            ->when($locationId, fn ($query) => $query->where('location_id', $locationId), fn ($query) => $query->whereNull('location_id'));
    }
}
