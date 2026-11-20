<?php

namespace App\Http\Resources\Pos;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A POS payment method as Settings → Point of Sale shows and edits it (Doc 18
 * Phase A1): its type, the account it takes money into - with its currency,
 * and whether it can still be used: active and taking customer payments -
 * whether it needs a reference and gives change, and whether it is on.
 */
class PosPaymentMethodResource extends JsonResource
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
            'needs_reference' => $this->needs_reference,
            'gives_change'    => $this->gives_change,
            'confirms_later'  => $this->confirms_later,
            'is_active'       => $this->is_active,
        ];
    }
}
