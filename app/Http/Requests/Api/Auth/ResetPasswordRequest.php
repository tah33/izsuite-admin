<?php

namespace App\Http\Requests\Api\Auth;

use App\Http\Requests\Api\Auth\Concerns\RejectsCredentialsInQueryString;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class ResetPasswordRequest extends FormRequest
{
    use RejectsCredentialsInQueryString;

    /** The token is a bearer credential and the password is a password. */
    private const BODY_ONLY_FIELDS = ['email', 'reset_token', 'password', 'password_confirmation'];

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->rejectCredentialsInQueryString(self::BODY_ONLY_FIELDS);

        if ($this->filled('email')) {
            $this->merge(['email' => strtolower(trim((string) $this->input('email')))]);
        }
    }

    /**
     * Same Password::defaults() as registration, the staff forms and the admin
     * profile - a reset is not a way in under a weaker rule.
     */
    public function rules(): array
    {
        return [
            'email'       => ['required', 'email', 'max:255'],
            'reset_token' => ['required', 'string'],
            'password'    => ['required', 'string', 'confirmed', Password::defaults()],
        ];
    }
}
