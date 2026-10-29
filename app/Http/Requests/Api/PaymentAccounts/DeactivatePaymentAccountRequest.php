<?php

namespace App\Http\Requests\Api\PaymentAccounts;

use Illuminate\Foundation\Http\FormRequest;

class DeactivatePaymentAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * A default account names the account that takes over each of its
     * defaults, where another could (Doc 05 section 8) - checked by
     * PaymentAccountService, which knows which could.
     */
    public function rules(): array
    {
        return [
            'replacement_receiving_id' => ['nullable', 'integer'],
            'replacement_payment_id'   => ['nullable', 'integer'],
        ];
    }
}
