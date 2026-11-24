<?php

namespace App\Http\Requests\Api\Ecommerce;

use Illuminate\Foundation\Http\FormRequest;

/**
 * A payment link for an online order (Doc 19 Phase F6a): the amount it asks,
 * how many days it works, and the store's methods it offers - one at least.
 * What the order owes, and whether it may ask less, is the service's to check.
 */
class SendEcommercePaymentLinkRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'amount'               => ['required', 'numeric', 'decimal:0,2', 'gt:0', 'max:9999999999999.99'],
            'days'                 => ['required', 'integer', 'min:1', 'max:90'],
            'payment_method_ids'   => ['required', 'array', 'min:1', 'max:20'],
            'payment_method_ids.*' => ['integer'],
        ];
    }

    public function messages(): array
    {
        return [
            'payment_method_ids.required' => 'Choose at least one way to pay.',
            'payment_method_ids.min'      => 'Choose at least one way to pay.',
        ];
    }
}
