<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/** The authenticator app's first code, which turns an admin's two-factor sign-in on. */
class ConfirmAdminTwoFactorRequest extends FormRequest
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
            'code.required' => 'Enter the 6-digit code your authenticator app shows.',
        ];
    }
}
