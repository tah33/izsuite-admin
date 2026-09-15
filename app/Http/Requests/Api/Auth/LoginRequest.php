<?php

namespace App\Http\Requests\Api\Auth;

use App\Http\Requests\Api\Auth\Concerns\RejectsCredentialsInQueryString;
use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    use RejectsCredentialsInQueryString;

    public function authorize(): bool
    {
        return true;
    }

    /** Fields that may only ever arrive in the request body. */
    private const BODY_ONLY_FIELDS = ['email', 'password'];

    protected function prepareForValidation(): void
    {
        $this->rejectCredentialsInQueryString(self::BODY_ONLY_FIELDS);

        if ($this->filled('email')) {
            $this->merge(['email' => strtolower(trim((string) $this->input('email')))]);
        }

        if (! $this->has('remember')) {
            return;
        }

        $normalized = filter_var($this->input('remember'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

        if ($normalized !== null) {
            $this->merge(['remember' => $normalized]);
        }
    }

    public function rules(): array
    {
        return [
            'email'       => ['required', 'email', 'max:255'],
            'password'    => ['required', 'string'],
            'remember'    => ['nullable', 'boolean'],
            'device_name' => ['nullable', 'string', 'max:255'],
        ];
    }
}
