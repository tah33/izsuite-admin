<?php

namespace App\Http\Requests\Api\Ecommerce;

use Illuminate\Foundation\Http\FormRequest;

/** The Quote Requests page's filters (Doc 19 Phase H4a): the storefront, and a number, name or e-mail searched for. */
class ListEcommerceQuoteRequestsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'storefront_id' => ['nullable', 'integer'],
            'search'        => ['nullable', 'string', 'max:100'],
        ];
    }
}
