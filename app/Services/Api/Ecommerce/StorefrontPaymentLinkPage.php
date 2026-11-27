<?php

namespace App\Services\Api\Ecommerce;

use App\Models\User\EcommerceOrder;
use App\Models\User\EcommercePaymentLink;
use App\Models\User\EcommercePaymentMethod;
use App\Models\User\Storefront;
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
 * expired, was revoked, or that the order is paid - or (Phase F6b) that its
 * customer reported paying through it, with what they reported
 * (StorefrontPaymentLinkReport). It shows while the store is there, open or
 * not: the order is the customer's.
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
        [$storefront, $link] = $this->find($code, $token);

        $order = $link->order;
        $owed  = $this->owed($order);
        $state = $this->stateOf($link, $order, $owed);

        return [
            'link'       => $link,
            'state'      => $state,
            'amount'     => $link->askedOf($owed),
            'expires_on' => $link->expires_at->copy()->setTimezone($this->calendar->timezone($storefront->workspace))->toDateString(),
            'methods'    => $state === EcommercePaymentLink::ACTIVE ? $this->offered($storefront, $link) : collect(),
        ];
    }

    /**
     * The store $code and its link carrying $token - each not found (404)
     * otherwise.
     *
     * @return array{0: Storefront, 1: EcommercePaymentLink}
     */
    public function find(string $code, string $token): array
    {
        $storefront = $this->catalog->findStore($code) ?? abort(404, 'There is no such store.');

        ActivityLogService::skipForThisRequest();

        return [$storefront, $this->links->findByToken($storefront, hash('sha256', $token)) ?? abort(404, 'There is no such payment link. Check the link you were sent.')];
    }

    /**
     * $link as its customer reads it: as kept (EcommercePaymentLink::state())
     * - but one still active or reported whose order is cancelled reads as
     * revoked, and one whose order owes nothing more as paid.
     */
    public function stateOf(EcommercePaymentLink $link, EcommerceOrder $order, string $owed): string
    {
        $state = $link->state();

        return match (true) {
            ! in_array($state, [EcommercePaymentLink::ACTIVE, EcommercePaymentLink::REPORTED], true) => $state,
            $order->status === EcommerceOrder::CANCELLED                                          => EcommercePaymentLink::REVOKED,
            bccomp($owed, '0', 2) <= 0                                                             => EcommercePaymentLink::PAID,
            default                                                                                => $state,
        };
    }

    /** What $order owes: its invoice's balance - or, before it has one, its total. */
    public function owed(EcommerceOrder $order): string
    {
        return (string) ($order->invoice ? $order->invoice->balance_due : $order->grand_total);
    }

    /** The methods $link offers that its store still takes, in its currency. */
    public function offered(Storefront $storefront, EcommercePaymentLink $link): Collection
    {
        return $this->methods->usableIn($storefront->workspace, $link->currency)->whereIn('id', array_column($link->payment_methods ?? [], 'id'))->values();
    }
}
