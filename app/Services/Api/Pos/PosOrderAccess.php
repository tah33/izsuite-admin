<?php

namespace App\Services\Api\Pos;

use App\Models\User\PosOrder;
use App\Models\User\Workspace;
use App\Repositories\User\PosOrderRepository;
use App\Services\Shared\WorkspaceAccess;

/**
 * Who may handle a completed POS sale's documents - its receipt (Phase C4)
 * and its formal invoice (Phase D4): one of the business's orders, at a
 * location the user may use, the one who sold it or whoever may reprint
 * receipts.
 */
class PosOrderAccess
{
    public function __construct(
        protected PosOrderRepository $orders,
        protected WorkspaceAccess $access,
    ) {}

    /** One of the business's orders, at a location the user may use - else as if there were none. */
    public function find(Workspace $workspace, int $id): PosOrder
    {
        return $this->orders->findFor($workspace, $id, $this->access->locations()) ?? abort(404, 'There is no such sale.');
    }

    /** Its cashier's - the one who sold it - or whoever may reprint receipts. */
    public function mayHandle(PosOrder $order): void
    {
        abort_unless($order->cashier_id === (int) $this->access->user()->id || $this->access->can('pos.receipts.reprint'), 403,
            "Receipt {$order->number} is its cashier's: anyone else needs Reprint Receipts.");
    }
}
