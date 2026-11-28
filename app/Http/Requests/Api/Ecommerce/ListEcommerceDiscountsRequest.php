<?php

namespace App\Http\Requests\Api\Ecommerce;

use Illuminate\Foundation\Http\FormRequest;

/** The Discounts page's search, by name or code (Doc 19 Phase H1) - and the products a discount may be for, by title; from Phase H2b its categories by name and customers by name or e-mail. */
class ListEcommerceDiscountsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
        ];
    }
}
