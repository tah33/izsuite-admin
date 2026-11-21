<?php

namespace App\Http\Requests\Api\Ecommerce;

use App\Models\User\EcommerceCart;
use Illuminate\Foundation\Http\FormRequest;

/** A cart line's new quantity (Doc 19 Phase D3): whole ones, one at least - Remove takes the line out. */
class UpdateStoreCartLineRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'quantity' => ['required', 'integer', 'min:1', 'max:'.EcommerceCart::MAX_QUANTITY],
        ];
    }

    public function messages(): array
    {
        return [
            'quantity.min' => 'Keep one at least, or remove it.',
            'quantity.max' => 'Up to '.EcommerceCart::MAX_QUANTITY.' of a product.',
        ];
    }
}
