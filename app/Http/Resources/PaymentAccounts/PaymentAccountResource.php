<?php

namespace App\Http\Resources\PaymentAccounts;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentAccountResource extends JsonResource
{
    /**
     * Every field, flat, as the table holds it (Doc 05 sections 5 to 9), with
     * its location by name. Balances go out as strings with two decimals
     * ("1500.00"), never as floats: the opening balance as the column holds
     * it, and the recorded balance PaymentAccountService worked out.
     * has_transactions says money has moved through it - which fixes its
     * currency and opening balance. Expects location loaded.
     */
    public function toArray(Request $request): array
    {
        return [
            'id'                      => $this->id,
            'type'                    => $this->type,
            'name'                    => $this->name,
            'currency'                => $this->currency,
            'location_id'             => $this->location_id,
            'location'                => $this->location?->name,
            'bank_name'               => $this->bank_name,
            'holder_name'             => $this->holder_name,
            'account_number'          => $this->account_number,
            'branch'                  => $this->branch,
            'swift_code'              => $this->swift_code,
            'bank_country'            => $this->bank_country,
            'merchant_name'           => $this->merchant_name,
            'merchant_number'         => $this->merchant_number,
            'settlement_currency'     => $this->settlement_currency,
            'opening_balance'         => $this->opening_balance,
            'opening_balance_date'    => $this->opening_balance_date,
            'balance'                 => $this->balance,
            'has_transactions'        => (bool) $this->has_transactions,
            'payment_instructions'    => $this->payment_instructions,
            'show_on_documents'       => $this->show_on_documents,
            'allow_customer_payments' => $this->allow_customer_payments,
            'allow_supplier_payments' => $this->allow_supplier_payments,
            'default_receiving'       => $this->default_receiving,
            'default_payment'         => $this->default_payment,
            'notes'                   => $this->notes,
            'is_active'               => $this->is_active,
        ];
    }
}
