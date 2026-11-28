<?php

namespace App\Http\Resources\Ecommerce;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Settings → eCommerce (Doc 19 Phase A1), as its form shows and saves them - from Phase C6b, the cookie notice too. */
class EcommerceSettingsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'guest_checkout'        => (bool) $this->guest_checkout,
            'verify_customer_email' => (bool) $this->verify_customer_email,
            'price_display'         => $this->price_display,
            'order_prefix'          => $this->order_prefix,
            'cookie_notice'         => (bool) $this->cookie_notice,
            'cookie_notice_text'    => $this->cookie_notice_text,
            'cart_days'             => (int) $this->cart_days,
            'checkout_phone'        => $this->checkout_phone,
            'checkout_notes'        => (bool) $this->checkout_notes,
            // Phase F4: Review Orders Above, in the base currency, and holding such orders as they are placed.
            'review_above'          => $this->review_above === null ? null : (string) $this->review_above,
            'hold_for_review'       => (bool) $this->hold_for_review,
            // Phase F6a: how many days a payment link works, and whether one may ask less than an order owes.
            'payment_link_days'     => (int) $this->payment_link_days,
            'partial_payments'      => (bool) $this->partial_payments,
            // Phase G3b: for how many days a delivered download works and how many times each file (null: no limit), and
            // whether downloading needs the customer signed in to their account.
            'download_days'          => $this->download_days,
            'download_limit'         => $this->download_limit,
            'downloads_need_account' => (bool) $this->downloads_need_account,
            // Phase H3a: how many hours a cart whose customer is known goes unused before it is Abandoned.
            'abandoned_hours'        => (int) $this->abandoned_hours,
            // Phase H4b, a choice from H4c: what a request for a quotation also becomes - nothing, lead or quotation.
            'quote_requests_become'  => $this->quote_requests_become,
        ];
    }
}
