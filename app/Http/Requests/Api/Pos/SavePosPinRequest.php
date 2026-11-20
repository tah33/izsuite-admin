<?php

namespace App\Http\Requests\Api\Pos;

use Illuminate\Foundation\Http\FormRequest;

class SavePosPinRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** A cashier's POS PIN (Doc 18 Phase I1): 4 to 6 digits, typed twice, with their account's password. */
    public function rules(): array
    {
        return [
            'pin'      => ['required', 'string', 'regex:/^\d{4,6}$/', 'confirmed'],
            'password' => ['required', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'pin.required'    => 'Choose a PIN.',
            'pin.regex'       => 'A PIN is 4 to 6 digits.',
            'pin.confirmed'   => 'The two PINs differ.',
            'password.required' => 'Your password is needed to set a PIN.',
        ];
    }
}
