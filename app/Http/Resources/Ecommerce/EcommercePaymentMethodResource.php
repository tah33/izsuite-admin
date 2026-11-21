<?php

namespace App\Http\Resources\Ecommerce;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A storefronts' payment method as Settings → eCommerce shows and edits it
 * (Doc 19 Phase D2): its type, the account it takes money into - with its
 * currency, and whether it can still be used: active and taking customer
 * payments - its instructions, whether customers give a reference, the
 * countries (none: every one), order amounts and delivery it is offered for,
 * and whether it is on.
 */
class EcommercePaymentMethodResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $account = $this->paymentAccount;

        return [
            'id'              => $this->id,
            'name'            => $this->name,
            'type'            => $this->type,
            'payment_account' => $account ? [
                'id'       => $account->id,
                'name'     => $account->name,
                'currency' => $account->currency,
                'usable'   => $account->is_active && $account->allow_customer_payments,
            ] : null,
            'instructions'    => $this->instructions,
            'reference'       => $this->reference,
            'countries'       => $this->countries ?? [],
            'min_order'       => $this->min_order,
            'max_order'       => $this->max_order,
            'delivery'        => $this->delivery,
            'is_active'       => $this->is_active,
        ];
    }
}
