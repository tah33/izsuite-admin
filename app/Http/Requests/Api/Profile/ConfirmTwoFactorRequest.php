<?php

namespace App\Http\Requests\Api\Profile;

use Illuminate\Foundation\Http\FormRequest;

/** The authenticator app's first code, which turns two-factor authentication on (Doc 12 section 22). */
class ConfirmTwoFactorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:10'],
        ];
    }

    public function messages(): array
    {
        return [
            'code.required' => 'Enter the code your authenticator app shows.',
        ];
    }
}
