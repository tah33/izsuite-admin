<?php

namespace App\Http\Requests\Api\Ecommerce;

use Illuminate\Foundation\Http\FormRequest;

/** An abandoned cart opened by its recovery e-mail's link (Doc 19 Phase H3b): the link's token. */
class RecoverStoreCartRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'token' => ['required', 'string', 'regex:/^[A-Za-z0-9]{20,100}$/'],
        ];
    }

    public function messages(): array
    {
        return [
            'token.required' => 'This link is not for a cart of this store. Add the products again.',
            'token.regex'    => 'This link is not for a cart of this store. Add the products again.',
        ];
    }
}
