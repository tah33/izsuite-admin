<?php

namespace App\Http\Requests\Api\Ecommerce;

use App\Models\User\EcommerceCart;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** The Abandoned Carts page's filters (Doc 19 Phase H3a): the storefront, the cart's status, and a customer's name or e-mail searched for. */
class ListEcommerceAbandonedCartsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'storefront_id' => ['nullable', 'integer'],
            'status'        => ['nullable', Rule::in(EcommerceCart::STATUSES)],
            'search'        => ['nullable', 'string', 'max:100'],
        ];
    }
}
