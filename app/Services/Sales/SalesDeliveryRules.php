<?php

namespace App\Services\Sales;

use App\Models\User\SalesDelivery;

/**
 * What a delivery note may do next (Doc 16 section 16.10). A draft is changed,
 * made ready - numbered - delivered or deleted; a ready one changed, delivered
 * or cancelled. Delivered and cancelled are final: what came back is a
 * return (Phase E). The API refuses what is not listed, and sends the list so
 * the page shows only those actions.
 */
final class SalesDeliveryRules
{
    /** @return list<string> */
    public function actions(SalesDelivery $delivery): array
    {
        return match ($delivery->status) {
            SalesDelivery::DRAFT => ['edit', 'ready', 'deliver', 'delete'],
            SalesDelivery::READY => ['edit', 'deliver', 'cancel'],
            // What was delivered can come back (Doc 16 Phase E).
            SalesDelivery::DELIVERED => ['return'],
            default              => [],
        };
    }

    public function allows(SalesDelivery $delivery, string $action): bool
    {
        return in_array($action, $this->actions($delivery), true);
    }
}
