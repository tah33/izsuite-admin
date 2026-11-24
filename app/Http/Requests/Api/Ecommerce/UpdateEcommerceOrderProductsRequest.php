<?php

namespace App\Http\Requests\Api\Ecommerce;

use App\Models\User\EcommerceCart;
use Illuminate\Foundation\Http\FormRequest;

/**
 * An online order's products as they are to be, before payment (Doc 19
 * Phase F5b): each of its own by id, with its quantity - those left out are
 * taken off - and each added by its listing, and its variant when it has
 * them. At least one, and no more than a cart holds.
 */
class UpdateEcommerceOrderProductsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'lines'                              => ['required', 'array', 'min:1', 'max:'.EcommerceCart::MAX_LINES],
            'lines.*'                            => ['array'],
            'lines.*.id'                         => ['nullable', 'integer'],
            'lines.*.catalog_product_id'         => ['required_without:lines.*.id', 'nullable', 'integer'],
            'lines.*.catalog_product_variant_id' => ['nullable', 'integer'],
            'lines.*.quantity'                   => ['required', 'integer', 'min:1', 'max:'.EcommerceCart::MAX_QUANTITY],
        ];
    }

    public function messages(): array
    {
        return [
            'lines.required' => 'Keep at least one product: to take them all off, cancel the order.',
            'lines.min'      => 'Keep at least one product: to take them all off, cancel the order.',
            'lines.max'      => 'An order holds up to '.EcommerceCart::MAX_LINES.' products.',
        ];
    }
}
