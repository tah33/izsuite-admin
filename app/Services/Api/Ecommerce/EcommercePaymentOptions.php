<?php

namespace App\Services\Api\Ecommerce;

use App\Models\User\EcommercePaymentMethod;
use App\Models\User\Workspace;
use App\Repositories\User\EcommercePaymentMethodRepository;
use Illuminate\Support\Collection;

/**
 * Which payment methods an order may be paid by (Doc 19 section 19.22 -
 * Phase D2), for the checkout (Phase D4): those on, into an account that is
 * active, takes customer payments and is in the order's currency; for the
 * order's country (where a method names some); within its amounts; and for
 * its delivery - a shipped order is offered those for shipped orders or
 * either, a picked-up one those for pickup or either, and one with nothing
 * to deliver those for either alone.
 */
class EcommercePaymentOptions
{
    /** An order with nothing to ship or collect: digital products and services. */
    public const NOTHING = 'none';

    public function __construct(
        protected EcommercePaymentMethodRepository $methods,
    ) {}

    /** @param  string  $delivery  shipped, pickup or none */
    public function for(Workspace $workspace, string $currency, ?string $country, string $amount, string $delivery): Collection
    {
        $country = strtoupper(trim((string) $country));

        return $this->methods->usableIn($workspace, $currency)
            ->filter(fn (EcommercePaymentMethod $method) => ($method->countries === null || in_array($country, $method->countries, true))
                && ($method->min_order === null || bccomp($amount, (string) $method->min_order, 2) >= 0)
                && ($method->max_order === null || bccomp($amount, (string) $method->max_order, 2) <= 0)
                && ($method->delivery === EcommercePaymentMethod::ANY || $method->delivery === $delivery))
            ->values();
    }
}
