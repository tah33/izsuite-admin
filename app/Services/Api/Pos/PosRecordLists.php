<?php

namespace App\Services\Api\Pos;

use App\Models\User\Workspace;
use App\Repositories\User\PosCashMovementRepository;
use App\Repositories\User\PosOrderRepository;
use App\Repositories\User\PosReturnRepository;
use App\Services\Shared\WorkspaceAccess;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * POS's own pages of records (Doc 18 section 18.3 - Phase G1): its Orders,
 * Returns and Refunds, and Cash Movements - each the latest first, at the
 * locations the user may use, by register, session and cashier, and by what
 * each page filters on besides.
 */
class PosRecordLists
{
    public function __construct(
        protected PosOrderRepository $orders,
        protected PosReturnRepository $returns,
        protected PosCashMovementRepository $movements,
        protected WorkspaceAccess $access,
    ) {}

    /** $filters are validated (see ListPosOrdersRequest). */
    public function orders(Workspace $workspace, array $filters, int $perPage): LengthAwarePaginator
    {
        return $this->orders->paginateFor($workspace, $this->access->locations(), $filters, $perPage);
    }

    /** $filters are validated (see ListPosReturnsRequest). */
    public function returns(Workspace $workspace, array $filters, int $perPage): LengthAwarePaginator
    {
        return $this->returns->paginateFor($workspace, $this->access->locations(), $filters, $perPage);
    }

    /** $filters are validated (see ListPosCashMovementsRequest). */
    public function movements(Workspace $workspace, array $filters, int $perPage): LengthAwarePaginator
    {
        return $this->movements->paginateFor($workspace, $this->access->locations(), $filters, $perPage);
    }
}
