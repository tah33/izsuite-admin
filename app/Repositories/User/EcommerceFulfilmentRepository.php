<?php

namespace App\Repositories\User;

use App\Models\User\EcommerceFulfilment;
use App\Models\User\EcommerceOrder;
use Illuminate\Support\Collection;

/**
 * Online orders' shipments and pickups (Doc 19 section 19.27 - Phase G1):
 * made for an order with its lines - numbered after its last - one of an
 * order's by id, locked, all of an order's with their lines, and one by id
 * for the e-mail that tells its customer.
 */
class EcommerceFulfilmentRepository
{
    /** Inside a transaction, $order locked: a fulfilment of $data with $lines ([order line id => quantity]), numbered after the order's last. */
    public function create(EcommerceOrder $order, array $data, array $lines): EcommerceFulfilment
    {
        $fulfilment = $order->fulfilments()->create([
            ...$data,
            'workspace_id' => $order->workspace_id,
            'sequence'     => (int) EcommerceFulfilment::query()->where('ecommerce_order_id', $order->id)->max('sequence') + 1,
        ]);

        foreach ($lines as $lineId => $quantity) {
            $fulfilment->lines()->create(['ecommerce_order_line_id' => $lineId, 'quantity' => $quantity]);
        }

        return $fulfilment->load('lines');
    }

    /** $order's fulfilment $id, locked, with its lines - none when it is not the order's. */
    public function findFor(EcommerceOrder $order, int $id): ?EcommerceFulfilment
    {
        return EcommerceFulfilment::query()->where('ecommerce_order_id', $order->id)->lockForUpdate()->with('lines')->find($id);
    }

    /** All of $order's fulfilments, cancelled ones too, with their lines. */
    public function ofOrder(EcommerceOrder $order): Collection
    {
        return EcommerceFulfilment::query()->where('ecommerce_order_id', $order->id)->with('lines')->orderBy('sequence')->get();
    }

    /** Fulfilment $id with its lines' products and where it came from - for its e-mail. */
    public function find(int $id): ?EcommerceFulfilment
    {
        return EcommerceFulfilment::query()->with(['lines.orderLine', 'location:id,name'])->find($id);
    }

    public function update(EcommerceFulfilment $fulfilment, array $data): void
    {
        $fulfilment->update($data);
    }

    /** $order's readiness for pickup not yet collected, cancelled with $reason - its order cancelled. How many were. */
    public function cancelReady(EcommerceOrder $order, string $reason): int
    {
        return EcommerceFulfilment::query()
            ->where('ecommerce_order_id', $order->id)
            ->where('status', EcommerceFulfilment::READY)
            ->update(['status' => EcommerceFulfilment::CANCELLED, 'cancel_reason' => mb_substr($reason, 0, 500), 'updated_at' => now()]);
    }
}
