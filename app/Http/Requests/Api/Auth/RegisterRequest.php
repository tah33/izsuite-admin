<?php

namespace App\Http\Requests\Api\Auth;

use App\Http\Requests\Api\Auth\Concerns\RejectsCredentialsInQueryString;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
    use RejectsCredentialsInQueryString;

    /** Titles the registration form's Prefix dropdown offers. */
    public const ALLOWED_PREFIXES  = ['Mr', 'Mrs', 'Miss', 'Ms', 'Dr'];

    /** Fields that may only ever arrive in the request body. */
    private const BODY_ONLY_FIELDS = ['email', 'username', 'password', 'password_confirmation'];

    public function authorize(): bool
    {
        return true;
    }

    /**
     * Email and username are both stored lower-cased and looked up that way, so
     * normalise here rather than trusting the caller's casing - otherwise
     * "Bob@x.com" and "bob@x.com" would register as two accounts.
     *
     * accept_terms arrives as a JSON boolean from the frontend but as the
     * string "true"/"on" from a form post; the `accepted` rule handles both, so
     * only the surrounding whitespace on the text fields needs work here.
     */
    protected function prepareForValidation(): void
    {
        $this->rejectCredentialsInQueryString(self::BODY_ONLY_FIELDS);

        $normalized = [];

        foreach (['email', 'username'] as $field) {
            if ($this->filled($field)) {
                $normalized[$field] = strtolower(trim((string) $this->input($field)));
            }
        }

        foreach (['prefix', 'first_name', 'last_name'] as $field) {
            if ($this->filled($field)) {
                $normalized[$field] = trim((string) $this->input($field));
            }
        }

        $this->merge($normalized);
    }

    public function rules(): array
    {
        return [
            'first_name'   => ['required', 'string', 'max:255'],
            'last_name'    => ['nullable', 'string', 'max:255'],
            'username'     => [
                'required', 'string', 'min:3', 'max:30',
                'regex:/^[a-z0-9][a-z0-9._-]*$/',
                Rule::unique('users', 'username'),
            ],

            'email'        => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'password'     => ['required', 'string', 'confirmed', Password::defaults()],
            'accept_terms' => ['required', 'accepted'],
        ];
    }

    public function attributes(): array
    {
        return [
            'accept_terms' => 'terms and conditions',
        ];
    }

    public function messages(): array
    {
        return [
            'username.regex'        => 'The username may only contain letters, numbers, dots, underscores and hyphens, and must start with a letter or number.',
            'accept_terms.accepted' => 'You must accept the terms and conditions to register.',
        ];
    }
}
