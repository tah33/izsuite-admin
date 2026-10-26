<?php

namespace App\Http\Resources\PaymentAccounts;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentAccountResource extends JsonResource
{
    /**
     * The opening balance goes out as a string with two decimals ("1500.00"),
     * exactly as the column holds it - never as a float.
     */
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id,
            'type'            => $this->type,
            'name'            => $this->name,
            'currency'        => $this->currency,
            'bank_name'       => $this->bank_name,
            'account_number'  => $this->account_number,
            'opening_balance' => $this->opening_balance,
            'notes'           => $this->notes,
            'is_active'       => $this->is_active,
        ];
    }
}
