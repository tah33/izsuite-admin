<?php

namespace App\Http\Requests\Api\Ecommerce;

use Illuminate\Foundation\Http\FormRequest;

/** An e-mail a storefront customer asks a code for (Doc 19 Phase E1): to verify it, or to reset their password. */
class StoreAccountEmailRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'email', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.required' => 'Give your e-mail.',
            'email.email'    => 'Give a valid e-mail address.',
        ];
    }
}
