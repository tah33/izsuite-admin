<?php

namespace App\Http\Resources\PaymentAccounts;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentAccountResource extends JsonResource
{
    /**
     * Balances go out as strings with two decimals ("1500.00"), never as
     * floats: the opening balance as the column holds it, and the recorded
     * balance PaymentAccountService worked out. has_transactions says money
     * has moved through it - which fixes its currency and opening balance.
     */
    public function toArray(Request $request): array
    {
        return [
            'id'                   => $this->id,
            'type'                 => $this->type,
            'name'                 => $this->name,
            'currency'             => $this->currency,
            'bank_name'            => $this->bank_name,
            'account_number'       => $this->account_number,
            'opening_balance'      => $this->opening_balance,
            'opening_balance_date' => $this->opening_balance_date,
            'balance'              => $this->balance,
            'has_transactions'     => (bool) $this->has_transactions,
            'notes'                => $this->notes,
            'is_active'            => $this->is_active,
        ];
    }
}
