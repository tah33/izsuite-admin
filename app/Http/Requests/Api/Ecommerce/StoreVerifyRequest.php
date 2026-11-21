<?php

namespace App\Http\Requests\Api\Ecommerce;

use Illuminate\Foundation\Http\FormRequest;

/** A storefront customer's e-mail and the code sent to it (Doc 19 Phase E1). */
class StoreVerifyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'email', 'max:255'],
            'code'  => ['required', 'digits:6'],
        ];
    }

    public function messages(): array
    {
        return [
            'code.required' => 'Type the code we sent you.',
            'code.digits'   => 'The code has six digits.',
        ];
    }
}
