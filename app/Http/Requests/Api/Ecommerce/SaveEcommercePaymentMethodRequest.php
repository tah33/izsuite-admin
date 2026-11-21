<?php

namespace App\Http\Requests\Api\Ecommerce;

use App\Models\User\EcommercePaymentMethod;
use App\Support\Countries;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A storefronts' payment method (Doc 19 Phase D2): its name, type, the
 * payment account it takes money into, its instructions, whether customers
 * give their payment's reference, the countries, order amounts and delivery
 * it is offered for, and whether it is on. EcommercePaymentMethodService
 * checks the rest.
 */
class SaveEcommercePaymentMethodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'               => ['required', 'string', 'max:100'],
            'type'               => ['required', Rule::in(EcommercePaymentMethod::TYPES)],
            'payment_account_id' => ['required', 'integer'],
            'instructions'       => ['sometimes', 'nullable', 'string', 'max:2000'],
            'reference'          => ['sometimes', Rule::in(EcommercePaymentMethod::REFERENCES)],
            'countries'          => ['sometimes', 'nullable', 'array', 'max:300'],
            'countries.*'        => ['string', Rule::in(Countries::codes())],
            'min_order'          => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:9999999999'],
            'max_order'          => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:9999999999'],
            'delivery'           => ['sometimes', Rule::in(EcommercePaymentMethod::DELIVERIES)],
            'is_active'          => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required'               => 'Give the method a name.',
            'payment_account_id.required' => 'Choose the payment account it takes money into.',
            'instructions.max'            => 'Keep the instructions to 2,000 characters.',
            'countries.*.in'              => 'Choose among the listed countries.',
        ];
    }
}
