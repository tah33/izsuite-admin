<?php

namespace App\Services\Api\Ecommerce;

use App\Models\User\EcommercePaymentLink;
use App\Models\User\EcommercePaymentMethod;
use App\Repositories\User\EcommercePaymentLinkRepository;
use App\Repositories\User\EcommercePaymentMethodRepository;
use App\Repositories\User\StorefrontCatalogRepository;
use App\Services\Shared\ActivityLogService;
use Illuminate\Support\Collection;

/**
 * A payment link's page on its store (Doc 19 section 19.23 - Phase F6a),
 * without signing in: found by its store and the token the link carries,
 * which only its customer was e-mailed. It shows what the link asks, for
 * which order and until when, and - while it is active - each method it
 * offers that the store still takes, with its instructions; else that it
 * expired, was revoked, or that the order is paid. It shows while the store
 * is there, open or not: the order is the customer's.
 */
class StorefrontPaymentLinkPage
{
    public function __construct(
        protected StorefrontCatalogRepository $catalog,
        protected EcommercePaymentLinkRepository $links,
        protected EcommercePaymentMethodRepository $methods,
    ) {}

    /** @return array{link: EcommercePaymentLink, state: string, methods: Collection<int, EcommercePaymentMethod>} */
    public function show(string $code, string $token): array
    {
        $storefront = $this->catalog->findStore($code) ?? abort(404, 'There is no such store.');

        ActivityLogService::skipForThisRequest();

        $link  = $this->links->findByToken($storefront, hash('sha256', $token)) ?? abort(404, 'There is no such payment link. Check the link you were sent.');
        $state = $link->state();

        return [
            'link'    => $link,
            'state'   => $state,
            'methods' => $state === EcommercePaymentLink::ACTIVE
                ? $this->methods->usableIn($storefront->workspace, $link->currency)->whereIn('id', array_column($link->payment_methods ?? [], 'id'))->values()
                : collect(),
        ];
    }
}
