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
        ];
    }
}
