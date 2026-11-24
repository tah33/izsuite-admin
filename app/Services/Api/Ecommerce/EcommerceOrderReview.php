<?php

namespace App\Services\Api\Ecommerce;

use App\Models\User\EcommerceOrder;
use App\Models\User\Workspace;
use App\Repositories\User\EcommerceOrderRepository;
use App\Repositories\User\EcommerceSettingRepository;
use App\Support\Countries;

/**
 * What may want a look at an online order (Doc 19 section 19.24 - Phase F4),
 * for the business - never its customer. Each assists a review, accusing no
 * one:
 *
 * - billed in one country and delivered to another;
 * - a total above Settings → eCommerce's Review Orders Above, in the base
 *   currency (none to start);
 * - its payment marked failed twice or more (Phase F3);
 * - its e-mail's third order or more within a day, up to it.
 *
 * The order's page shows them; with Hold Orders for Review on, an order that
 * has one is held as it is placed (EcommerceOrderHolds::holdAsPlaced()).
 */
class EcommerceOrderReview
{
    public function __construct(
        protected EcommerceOrderRepository $orders,
        protected EcommerceSettingRepository $settings,
    ) {}

    /** @return list<string> */
    public function notes(Workspace $workspace, EcommerceOrder $order): array
    {
        $above    = $this->settings->forWorkspace($workspace)->review_above;
        $billing  = $order->billing_address['country'] ?? null;
        $shipping = $order->shipping_address['country'] ?? null;
        $placed   = $order->placed_at ? $this->orders->placedFromEmail($workspace, $order->customer_email, $order->placed_at->copy()->subDay(), $order->placed_at) : 0;
        $base     = $workspace->base_currency;

        return array_values(array_filter([
            $billing && $shipping && $billing !== $shipping
                ? 'Billed in '.(Countries::name($billing) ?? $billing).' but delivered to '.(Countries::name($shipping) ?? $shipping).'.'
                : null,
            $above !== null && bccomp((string) $order->base_grand_total, (string) $above, 2) > 0
                ? "Its total, {$base} {$order->base_grand_total}, is above Review Orders Above ({$base} {$above})."
                : null,
            $order->payment_failures >= 2 ? "Its payment was marked failed {$order->payment_failures} times." : null,
            $placed >= 3 ? "{$order->customer_email} placed {$placed} orders within a day, up to this one." : null,
        ]));
    }
}
