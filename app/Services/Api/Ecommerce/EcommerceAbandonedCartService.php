<?php

namespace App\Services\Api\Ecommerce;

use App\Models\User\Workspace;
use App\Repositories\User\EcommerceCartRepository;
use App\Services\Shared\WorkspaceAccess;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * The business's abandoned carts (Doc 19 section 19.32 - Phase H3a), under
 * View Orders: the Abandoned Carts page - each cart that was abandoned, as it
 * stands now. A cart is seen where its storefront's location may be used, as
 * an order is. A cart is never an order, a sale or a document.
 */
class EcommerceAbandonedCartService
{
    public function __construct(
        protected EcommerceCartRepository $carts,
        protected WorkspaceAccess $access,
    ) {}

    /** $filters: storefront_id, status and search. */
    public function list(Workspace $workspace, array $filters, int $perPage): LengthAwarePaginator
    {
        return $this->carts->abandonedFor($workspace, $this->access->locations(), $filters, $perPage);
    }
}
