<?php

namespace App\Http\Requests\Api\Auth;

use App\Http\Requests\Api\Auth\Concerns\RejectsCredentialsInQueryString;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class ChangePasswordRequest extends FormRequest
{
    use RejectsCredentialsInQueryString;

    private const BODY_ONLY_FIELDS = ['current_password', 'password', 'password_confirmation'];

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->rejectCredentialsInQueryString(self::BODY_ONLY_FIELDS);
    }

    /**
     * The same two rules the admin profile form applies, so one password policy
     * covers the whole product - see UpdateAdminProfilePasswordRequest.
     *
     * The `:sanctum` on current_password is not decoration. The rule resolves a
     * guard, and with no argument it takes the default one - `web` - which on a
     * bearer-token request holds nobody, so every submission would be rejected
     * as a wrong current password.
     */
    public function rules(): array
    {
        return [
            'current_password' => ['required', 'current_password:sanctum'],
            'password'         => ['required', 'confirmed', Password::defaults()],
        ];
    }

    public function messages(): array
    {
        return [
            'current_password.current_password' => 'That is not your current password.',
        ];
    }
}
