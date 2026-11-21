<?php

namespace App\Services\Api\Ecommerce;

use App\Models\User\CatalogProduct;
use App\Models\User\StorefrontCustomer;

/**
 * Who a storefront's request is from (Doc 19 Phase E3): a visitor, or the
 * customer signed in by X-Customer-Token - set by the store.customer
 * middleware for the request alone. What the store shows and its prices
 * follow it:
 *
 * - its lists, search and facets show the listings for everyone, and those
 *   kept for signed-in customers to a customer signed in; a listing kept for
 *   its link opens at its link, as one for signed-in customers does for
 *   them (StorefrontCatalogRepository);
 * - with full Sales, a customer's own price lists come before the store's
 *   (StorefrontPricing).
 */
class StoreVisitor
{
    private ?StorefrontCustomer $customer = null;

    public function signIn(?StorefrontCustomer $customer): void
    {
        $this->customer = $customer;
    }

    public function customer(): ?StorefrontCustomer
    {
        return $this->customer;
    }

    /** The listings' visibilities shown in the store's lists. */
    public function listed(): array
    {
        return $this->customer ? [CatalogProduct::VISIBLE_PUBLIC, CatalogProduct::VISIBLE_CUSTOMERS] : [CatalogProduct::VISIBLE_PUBLIC];
    }

    /** The visibilities a listing opens at its link with: those listed, and those kept for their link. */
    public function reachable(): array
    {
        return [...$this->listed(), CatalogProduct::VISIBLE_LINK];
    }
}
