<?php

namespace App\Http\Requests\Api\Ecommerce;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

/** A storefront customer's new password, with the code sent to their e-mail (Doc 19 Phase E1). */
class StoreResetPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email'    => ['required', 'email', 'max:255'],
            'code'     => ['required', 'digits:6'],
            'password' => ['required', 'string', 'confirmed', Password::defaults()],
        ];
    }

    public function messages(): array
    {
        return [
            'code.required'      => 'Type the code we sent you.',
            'code.digits'        => 'The code has six digits.',
            'password.confirmed' => 'The two passwords differ.',
        ];
    }
}
