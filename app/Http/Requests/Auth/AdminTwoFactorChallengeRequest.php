<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

/** The code step of the admin sign-in: the app's code, or a recovery code. */
class AdminTwoFactorChallengeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code'          => ['nullable', 'required_without:recovery_code', 'string', 'max:10'],
            'recovery_code' => ['nullable', 'required_without:code', 'string', 'max:20'],
        ];
    }

    public function messages(): array
    {
        return [
            'code.required_without'          => 'Enter the 6-digit code from your authenticator app.',
            'recovery_code.required_without' => 'Enter one of your recovery codes.',
        ];
    }
}
