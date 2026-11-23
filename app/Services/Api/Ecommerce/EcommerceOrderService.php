<?php

namespace App\Services\Api\Ecommerce;

use App\Models\User\EcommerceOrder;
use App\Models\User\Workspace;
use App\Repositories\User\EcommerceOrderRepository;
use App\Services\Shared\WorkspaceAccess;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * The business's online orders (Doc 19 sections 19.18 and 19.19 - Phase F1),
 * under View Orders: the Orders page and each order's page. An order is
 * seen where its location may be used - as its storefront is - and to
 * anyone else it is not found. Its timeline is read from the Activity Log
 * (RecordActivityService), as a sales document's is.
 */
class EcommerceOrderService
{
    public function __construct(
        protected EcommerceOrderRepository $orders,
        protected WorkspaceAccess $access,
    ) {}

    /** $filters: storefront_id, status, payment_status, fulfilment_status and search. */
    public function list(Workspace $workspace, array $filters, int $perPage): LengthAwarePaginator
    {
        return $this->orders->paginateFor($workspace, $this->access->locations(), $filters, $perPage);
    }

    public function find(Workspace $workspace, int $id): EcommerceOrder
    {
        return $this->orders->findFor($workspace, $id, $this->access->locations()) ?? abort(404, 'There is no such order.');
    }

    /** What the Activity Log calls it. */
    public function describe(EcommerceOrder $order): string
    {
        return "online order {$order->number}";
    }
}
