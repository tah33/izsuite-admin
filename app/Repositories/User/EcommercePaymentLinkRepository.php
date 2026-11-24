<?php

namespace App\Repositories\User;

use App\Models\User\EcommerceOrder;
use App\Models\User\EcommercePaymentLink;
use App\Models\User\Storefront;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Online orders' payment links (Doc 19 section 19.23 - Phase F6a): made for
 * an order, its links still active, one of them by id, and a link by its
 * token's hash on its order's store.
 */
class EcommercePaymentLinkRepository
{
    public function create(EcommerceOrder $order, array $data): EcommercePaymentLink
    {
        return $order->paymentLinks()->create([...$data, 'workspace_id' => $order->workspace_id]);
    }

    /** $order's links kept active - past their day or not - locked when $lock. */
    public function activeFor(EcommerceOrder $order, bool $lock): Collection
    {
        return EcommercePaymentLink::query()
            ->where('ecommerce_order_id', $order->id)
            ->where('status', EcommercePaymentLink::ACTIVE)
            ->when($lock, fn (Builder $query) => $query->lockForUpdate())
            ->get();
    }

    /** $order's link $id, locked - none when it is not the order's. */
    public function findFor(EcommerceOrder $order, int $id): ?EcommercePaymentLink
    {
        return EcommercePaymentLink::query()->where('ecommerce_order_id', $order->id)->lockForUpdate()->find($id);
    }

    public function update(EcommercePaymentLink $link, array $data): void
    {
        $link->update($data);
    }

    /** The link whose token hashes to $hash, of an order of $storefront - with the order as its page shows it. */
    public function findByToken(Storefront $storefront, string $hash): ?EcommercePaymentLink
    {
        return EcommercePaymentLink::query()
            ->where('token_hash', $hash)
            ->whereHas('order', fn (Builder $order) => $order->where('storefront_id', $storefront->id))
            ->with(['order.lines', 'order.invoice:id,balance_due'])
            ->first();
    }
}
