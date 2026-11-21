<?php

namespace App\Repositories\User;

use App\Models\User\EcommercePaymentMethod;
use App\Models\User\Workspace;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * The storefronts' payment methods without a gateway (Doc 19 Phase D2), in
 * their order: as Settings → eCommerce edits them, and as a checkout offers
 * them - those on, whose account is active and takes customer payments.
 */
class EcommercePaymentMethodRepository
{
    /** What a method's row names besides itself: its account, as the settings show it. */
    private const WITH = ['paymentAccount:id,name,currency,type,is_active,allow_customer_payments'];

    /** Its methods in their order, each with its account. */
    public function methods(Workspace $workspace): Collection
    {
        return $workspace->ecommercePaymentMethods()->with(self::WITH)->orderBy('position')->orderBy('id')->get();
    }

    /**
     * Its methods a checkout may offer, in their order: on, into an account
     * that is active and takes customer payments, in $currency.
     */
    public function usableIn(Workspace $workspace, string $currency): Collection
    {
        return $workspace->ecommercePaymentMethods()
            ->where('is_active', true)
            ->whereHas('paymentAccount', fn (Builder $account) => $account->where('is_active', true)->where('allow_customer_payments', true)->where('currency', $currency))
            ->with(self::WITH)
            ->orderBy('position')
            ->orderBy('id')
            ->get();
    }

    public function find(Workspace $workspace, int $id): ?EcommercePaymentMethod
    {
        return $workspace->ecommercePaymentMethods()->with(self::WITH)->find($id);
    }

    /** Whether another of its methods - $exceptId aside - is called $name. */
    public function nameTaken(Workspace $workspace, string $name, ?int $exceptId = null): bool
    {
        return $workspace->ecommercePaymentMethods()->where('name', $name)->when($exceptId, fn (Builder $query, int $id) => $query->whereKeyNot($id))->exists();
    }

    /** The place after its last method. */
    public function nextPosition(Workspace $workspace): int
    {
        return (int) $workspace->ecommercePaymentMethods()->max('position') + 1;
    }

    /** Made through the business, so its workspace is never read from $data. */
    public function create(Workspace $workspace, array $data): EcommercePaymentMethod
    {
        return $workspace->ecommercePaymentMethods()->create($data)->load(self::WITH);
    }

    public function update(EcommercePaymentMethod $method, array $data): EcommercePaymentMethod
    {
        $method->update($data);

        return $method->load(self::WITH);
    }

    public function delete(EcommercePaymentMethod $method): void
    {
        $method->delete();
    }
}
