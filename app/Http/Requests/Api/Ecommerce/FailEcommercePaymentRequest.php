<?php

namespace App\Http\Requests\Api\Ecommerce;

use Illuminate\Foundation\Http\FormRequest;

/** An online order's payment marked failed (Doc 19 Phase F3): why, for its timeline - never shown to the customer. */
class FailEcommercePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return ['reason.required' => 'Say why the payment failed: it stays in the order\'s timeline, not in the e-mail to the customer.'];
    }
}
