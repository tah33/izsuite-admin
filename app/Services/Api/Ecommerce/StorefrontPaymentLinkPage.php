<?php

namespace App\Services\Api\Ecommerce;

use App\Models\User\EcommerceOrder;
use App\Models\User\EcommercePaymentLink;
use App\Models\User\EcommercePaymentMethod;
use App\Repositories\User\EcommercePaymentLinkRepository;
use App\Repositories\User\EcommercePaymentMethodRepository;
use App\Repositories\User\StorefrontCatalogRepository;
use App\Services\Shared\ActivityLogService;
use App\Services\Shared\BusinessCalendar;
use Illuminate\Support\Collection;

/**
 * A payment link's page on its store (Doc 19 section 19.23 - Phase F6a),
 * without signing in: found by its store and the token the link carries,
 * which only its customer was e-mailed. It shows what the link asks - never
 * more than the order still owes, paid in part since - for which order and
 * until which day of the business's, and while it is active each method it
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
        protected BusinessCalendar $calendar,
    ) {}

    /** @return array{link: EcommercePaymentLink, state: string, amount: string, expires_on: string, methods: Collection<int, EcommercePaymentMethod>} */
    public function show(string $code, string $token): array
    {
        $storefront = $this->catalog->findStore($code) ?? abort(404, 'There is no such store.');

        ActivityLogService::skipForThisRequest();

        $link  = $this->links->findByToken($storefront, hash('sha256', $token)) ?? abort(404, 'There is no such payment link. Check the link you were sent.');
        $order = $link->order;
        $owed  = (string) ($order->invoice ? $order->invoice->balance_due : $order->grand_total);

        // Kept active, a link whose order owes nothing more reads as paid, and one whose order was cancelled as revoked.
        $state = match (true) {
            $link->state() !== EcommercePaymentLink::ACTIVE  => $link->state(),
            $order->status === EcommerceOrder::CANCELLED     => EcommercePaymentLink::REVOKED,
            bccomp($owed, '0', 2) <= 0                        => EcommercePaymentLink::PAID,
            default                                          => EcommercePaymentLink::ACTIVE,
        };

        return [
            'link'       => $link,
            'state'      => $state,
            'amount'     => bccomp($owed, (string) $link->amount, 2) < 0 && bccomp($owed, '0', 2) > 0 ? $owed : (string) $link->amount,
            'expires_on' => $link->expires_at->copy()->setTimezone($this->calendar->timezone($storefront->workspace))->toDateString(),
            'methods'    => $state === EcommercePaymentLink::ACTIVE
                ? $this->methods->usableIn($storefront->workspace, $link->currency)->whereIn('id', array_column($link->payment_methods ?? [], 'id'))->values()
                : collect(),
        ];
    }
}
