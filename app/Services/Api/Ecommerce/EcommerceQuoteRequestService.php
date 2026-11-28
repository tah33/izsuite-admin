<?php

namespace App\Services\Api\Ecommerce;

use App\Models\User\EcommerceQuoteRequest;
use App\Models\User\Workspace;
use App\Repositories\User\EcommerceQuoteRequestRepository;
use App\Services\Shared\WorkspaceAccess;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * The business's requests for a quotation (Doc 19 section 19.30 - Phase
 * H4a), under View Orders: the Quote Requests page, and one request whole. A
 * request is seen where its storefront's location may be used, as an order
 * is, and to anyone else it is not found.
 */
class EcommerceQuoteRequestService
{
    public function __construct(
        protected EcommerceQuoteRequestRepository $requests,
        protected WorkspaceAccess $access,
    ) {}

    /** $filters: storefront_id and search. */
    public function list(Workspace $workspace, array $filters, int $perPage): LengthAwarePaginator
    {
        return $this->requests->paginateFor($workspace, $this->access->locations(), $filters, $perPage);
    }

    public function find(Workspace $workspace, int $id): EcommerceQuoteRequest
    {
        return $this->requests->findFor($workspace, $id, $this->access->locations()) ?? abort(404, 'There is no such request.');
    }
}
