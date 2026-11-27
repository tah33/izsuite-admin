<?php

namespace App\Repositories\User;

use App\Models\User\EcommerceOrder;
use App\Models\User\EcommercePaymentLink;
use App\Models\User\Storefront;
use Illuminate\Database\Eloquent\Builder;

/**
 * Online orders' payment links (Doc 19 section 19.23 - Phase F6a): made for
 * an order, one of an order's by id, an order's links still active revoked
 * or marked paid, and a link by its token's hash on its order's store - and
 * (Phase F6b) the order's link its customer reported paying through.
 */
class EcommercePaymentLinkRepository
{
    public function create(EcommerceOrder $order, array $data): EcommercePaymentLink
    {
        return $order->paymentLinks()->create([...$data, 'workspace_id' => $order->workspace_id]);
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

    /**
     * $order's links still active - past their day or not - revoked, by
     * $userId (none: izSuite), $why: by hand, replaced by a newer link, the
     * order cancelled, or its total changed. How many were.
     */
    public function revokeActive(EcommerceOrder $order, ?int $userId, string $why): int
    {
        return $this->active($order)->update([
            'status'      => EcommercePaymentLink::REVOKED,
            'revoked_at'  => now(),
            'revoked_by'  => $userId,
            'revoked_why' => $why,
            'updated_at'  => now(),
        ]);
    }

    /**
     * Phase F6b: $order's link still active that its customer reported
     * paying through - one at most, as one link is active at a time - locked
     * when $lock.
     */
    public function reportedFor(EcommerceOrder $order, bool $lock): ?EcommercePaymentLink
    {
        return $this->active($order)->whereNotNull('reported_at')->when($lock, fn (Builder $query) => $query->lockForUpdate())->latest('id')->first();
    }

    /** $order's links still active marked paid: the order owes nothing more. How many were. */
    public function markActivePaid(EcommerceOrder $order): int
    {
        return $this->active($order)->update(['status' => EcommercePaymentLink::PAID, 'updated_at' => now()]);
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

    private function active(EcommerceOrder $order): Builder
    {
        return EcommercePaymentLink::query()->where('ecommerce_order_id', $order->id)->where('status', EcommercePaymentLink::ACTIVE);
    }
}
