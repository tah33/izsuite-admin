<?php

namespace App\Http\Requests\Api\Auth;

use Illuminate\Foundation\Http\FormRequest;

class ResendVerificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('email')) {
            $this->merge(['email' => strtolower(trim((string) $this->input('email')))]);
        }
    }

    /**
     * Same trade as ForgotPasswordRequest: register already reveals which
     * addresses are taken, so refusing to say so here only left a real user
     * unable to tell a typo from a mail that never arrived.
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'email', 'max:255', 'exists:users,email'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.exists' => "We couldn't find an account with that email address.",
        ];
    }
}
