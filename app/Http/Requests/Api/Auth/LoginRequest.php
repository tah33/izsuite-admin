<?php

namespace App\Http\Requests\Api\Auth;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** Fields that may only ever arrive in the request body. */
    private const BODY_ONLY_FIELDS = ['email', 'password'];


    protected function prepareForValidation(): void
    {
        $this->rejectCredentialsInQueryString();

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

    /**
     * Laravel's input() reads the query string as well as the body, so without
     * this a caller could sign in via POST /auth/login?email=..&password=..
     *
     * A URL is not a private channel: it is written verbatim into web-server
     * access logs, browser history, bookmarks and outgoing Referer headers, so
     * a password sent that way is a password on disk in half a dozen places.
     *
     * Refusing the request is deliberate - quietly ignoring the parameters
     * would leave a client "working" while still putting secrets in URLs, and
     * the leak would only surface later in a log file.
     */
    private function rejectCredentialsInQueryString(): void
    {
        $offending = array_intersect(self::BODY_ONLY_FIELDS, array_keys($this->query->all()));

        if ($offending === []) {
            return;
        }

        abort(400, sprintf(
            'Send %s in the request body, not the query string.',
            implode(' and ', $offending),
        ));
    }
}
