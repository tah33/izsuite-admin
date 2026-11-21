<?php

namespace App\Http\Requests\Api\Ecommerce;

use Illuminate\Foundation\Http\FormRequest;

/**
 * A storefront customer's profile (Doc 19 Phase E1): their name and phone -
 * and, from Phase E3, their language among the store's and, their e-mail
 * verified, whether they take marketing by e-mail. StorefrontCustomerAccount
 * checks the language against the store.
 */
class SaveStoreAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'      => ['required', 'string', 'max:150'],
            'phone'     => ['nullable', 'string', 'max:40'],
            'language'  => ['sometimes', 'nullable', 'string', 'max:10'],
            'marketing' => ['sometimes', 'nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Give your name.',
        ];
    }
}
