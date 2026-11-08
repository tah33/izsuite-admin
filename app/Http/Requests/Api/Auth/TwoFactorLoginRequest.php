<?php

namespace App\Http\Requests\Api\Auth;

use Illuminate\Foundation\Http\FormRequest;

/**
 * A sign-in's second step with two-factor authentication on (Doc 12 section
 * 22): the challenge the password step gave, and the authenticator app's
 * six-digit code - or, without the app, one of the recovery codes.
 */
class TwoFactorLoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'challenge'     => ['required', 'string', 'size:40'],
            'code'          => ['nullable', 'required_without:recovery_code', 'string', 'max:10'],
            'recovery_code' => ['nullable', 'required_without:code', 'string', 'max:20'],
        ];
    }

    public function messages(): array
    {
        return [
            'code.required_without'          => 'Enter the code from your authenticator app.',
            'recovery_code.required_without' => 'Enter one of your recovery codes.',
        ];
    }
}
