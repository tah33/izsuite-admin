<?php

namespace App\Http\Requests\Api\Ecommerce;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

/** A storefront customer signing up (Doc 19 Phase E1): their name, e-mail, a password by izSuite's rules, and a phone if they like. */
class StoreSignUpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'     => ['required', 'string', 'max:150'],
            'email'    => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'confirmed', Password::defaults()],
            'phone'    => ['nullable', 'string', 'max:40'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required'      => 'Give your name.',
            'email.required'     => 'Give your e-mail.',
            'email.email'        => 'Give a valid e-mail address.',
            'password.confirmed' => 'The two passwords differ.',
        ];
    }
}
