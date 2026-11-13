<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Resetting a user's two-factor sign-in (Doc 15 section 15.7): how support
 * checked who is asking, then the admin's password again and a reason, which
 * CriticalActionService checks (section 15.25).
 */
class ResetClientTwoFactorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'identity_check'   => ['required', 'string', 'min:5', 'max:500'],
            'confirm_password' => ['nullable', 'string', 'max:255'],
            'reason'           => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'identity_check.required' => 'Say how you checked that the person asking owns the account.',
            'identity_check.min'      => 'Say how you checked that the person asking owns the account.',
        ];
    }
}
