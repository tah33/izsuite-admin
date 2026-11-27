<?php

namespace App\Repositories\User;

use App\Models\User\EcommerceOrder;
use App\Models\User\EcommerceOrderDelivery;
use App\Models\User\EcommerceOrderLine;
use Illuminate\Support\Collection;

/**
 * Online orders' digital products delivered and services performed (Doc 19
 * sections 19.28 and 19.29 - Phase G3a): one made for an order's line, all of
 * an order's, and one changed.
 */
class EcommerceOrderDeliveryRepository
{
    /** Inside a transaction, $order locked: $line's delivery, of $data. */
    public function create(EcommerceOrder $order, EcommerceOrderLine $line, array $data): EcommerceOrderDelivery
    {
        return EcommerceOrderDelivery::query()->create([
            ...$data,
            'workspace_id'            => $order->workspace_id,
            'ecommerce_order_id'      => $order->id,
            'ecommerce_order_line_id' => $line->id,
        ]);
    }

    /** All of $order's, keyed by their order line. */
    public function ofOrder(EcommerceOrder $order): Collection
    {
        return EcommerceOrderDelivery::query()->where('ecommerce_order_id', $order->id)->get()->keyBy('ecommerce_order_line_id');
    }

    public function update(EcommerceOrderDelivery $delivery, array $data): void
    {
        $delivery->update($data);
    }
}
