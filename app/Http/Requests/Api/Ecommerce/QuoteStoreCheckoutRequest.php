<?php

namespace App\Http\Requests\Api\Ecommerce;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * What a checkout is worked out for (Doc 19 Phase D4), as its customer fills
 * it in: the address it is delivered to, the billing country, and the
 * delivery or pickup and the payment chosen so far - any of them yet to come.
 */
class QuoteStoreCheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'address'             => ['sometimes', 'nullable', 'array'],
            'address.country'     => ['nullable', 'string', 'max:2'],
            'address.region'      => ['nullable', 'string', 'max:100'],
            'address.city'        => ['nullable', 'string', 'max:100'],
            'address.postal_code' => ['nullable', 'string', 'max:20'],
            'billing_country'     => ['nullable', 'string', 'max:2'],
            'delivery'            => ['nullable', Rule::in(['shipping', 'pickup'])],
            'shipping_method_id'  => ['nullable', 'integer'],
            'pickup_point_id'     => ['nullable', 'integer'],
            'payment_method_id'   => ['nullable', 'integer'],
        ];
    }
}
