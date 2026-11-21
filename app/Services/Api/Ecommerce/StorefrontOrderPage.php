<?php

namespace App\Services\Api\Ecommerce;

use App\Models\User\EcommerceOrder;
use App\Repositories\User\EcommerceOrderRepository;
use App\Repositories\User\StorefrontCatalogRepository;
use App\Services\Shared\ActivityLogService;

/**
 * An online order's own page for its customer (Doc 19 section 19.20 - Phase
 * D5), without signing in: found by its store and the key its link carries,
 * which only the customer was given - at checkout and in its e-mail. It shows
 * while the store is there, open or not: the order is the customer's.
 * Signed-in customers' orders come with Phase E.
 */
class StorefrontOrderPage
{
    public function __construct(
        protected StorefrontCatalogRepository $catalog,
        protected EcommerceOrderRepository $orders,
    ) {}

    public function show(string $code, string $key): EcommerceOrder
    {
        $storefront = $this->catalog->findStore($code) ?? abort(404, 'There is no such store.');

        ActivityLogService::skipForThisRequest();

        return $this->orders->forCustomer($storefront, hash('sha256', $key)) ?? abort(404, 'There is no such order. Check the link you were sent.');
    }
}
