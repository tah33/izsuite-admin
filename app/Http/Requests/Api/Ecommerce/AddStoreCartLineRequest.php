<?php

namespace App\Http\Requests\Api\Ecommerce;

use App\Models\User\EcommerceCart;
use Illuminate\Foundation\Http\FormRequest;

/** A product added to a storefront's cart (Doc 19 Phase D3): its link, its variant when it has them, and how many - whole ones. */
class AddStoreCartLineRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'product'    => ['required', 'string', 'max:200', 'regex:/^[A-Za-z0-9-]+$/'],
            'variant_id' => ['sometimes', 'nullable', 'integer'],
            'quantity'   => ['required', 'integer', 'min:1', 'max:'.EcommerceCart::MAX_QUANTITY],
        ];
    }

    public function messages(): array
    {
        return [
            'quantity.min' => 'Add one at least.',
            'quantity.max' => 'Up to '.EcommerceCart::MAX_QUANTITY.' of a product.',
        ];
    }
}
