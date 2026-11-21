<?php

namespace App\Http\Requests\Api\Ecommerce;

use App\Support\Countries;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** An address a storefront customer keeps (Doc 19 Phase E2), as checkout takes one, and whether it is their default for billing and for delivery. */
class SaveStoreAddressRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'line1'            => ['required', 'string', 'max:200'],
            'line2'            => ['nullable', 'string', 'max:200'],
            'city'             => ['required', 'string', 'max:100'],
            'region'           => ['nullable', 'string', 'max:100'],
            'postal_code'      => ['nullable', 'string', 'max:20'],
            'country'          => ['required', 'string', Rule::in(Countries::codes())],
            'default_billing'  => ['sometimes', 'boolean'],
            'default_shipping' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'line1.required'   => 'Give the address.',
            'city.required'    => 'Give its city.',
            'country.required' => 'Choose its country.',
            'country.in'       => 'Choose among the listed countries.',
        ];
    }
}
