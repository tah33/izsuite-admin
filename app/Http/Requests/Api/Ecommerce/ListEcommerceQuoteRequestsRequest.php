<?php

namespace App\Http\Requests\Api\Ecommerce;

use App\Models\User\EcommerceQuoteRequest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** The Quote Requests page's filters (Doc 19 Phase H4a): the storefront, and a number, name or e-mail searched for - and (Phase H4e) the kind, the Messages page's `message`. */
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
            'kind'          => ['nullable', Rule::in([EcommerceQuoteRequest::KIND_QUOTE, EcommerceQuoteRequest::KIND_MESSAGE])],
        ];
    }
}
