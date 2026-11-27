<?php

namespace App\Http\Requests\Api\Ecommerce;

use Illuminate\Foundation\Http\FormRequest;

/**
 * A digital product delivered by the business (Doc 19 Phase G3a): a license
 * key product's keys, typed in; a delivery by hand's note to the customer;
 * or, for a revoked one, nothing - its access given again. Which is needed
 * depends on how the product is delivered (EcommerceOrderDeliveries).
 */
class DeliverEcommerceProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'license_keys'   => ['nullable', 'array', 'max:100'],
            'license_keys.*' => ['nullable', 'string', 'max:255'],
            'note'           => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'license_keys.max'   => 'Give at most 100 license keys.',
            'license_keys.*.max' => 'Keep each license key within 255 characters.',
        ];
    }
}
