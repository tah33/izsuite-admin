<?php

namespace App\Http\Requests\Api\Auth;

use App\Http\Requests\Api\Auth\Concerns\RejectsCredentialsInQueryString;
use App\Services\Api\Auth\EmailVerificationService;
use Illuminate\Foundation\Http\FormRequest;

class VerifyEmailRequest extends FormRequest
{
    use RejectsCredentialsInQueryString;

    /**
     * The code is a credential with a 30 minute life, so it is kept out of the
     * URL for the same reason a password is.
     */
    private const BODY_ONLY_FIELDS = ['email', 'otp'];

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->rejectCredentialsInQueryString(self::BODY_ONLY_FIELDS);

        $normalized = [];

        if ($this->filled('email')) {
            $normalized['email'] = strtolower(trim((string) $this->input('email')));
        }

        // The OtpInput pastes whatever the clipboard held, so spaces and the
        // dashes people copy out of an email arrive attached to the digits.
        if ($this->filled('otp')) {
            $normalized['otp'] = preg_replace('/\D/', '', (string) $this->input('otp'));
        }

        $this->merge($normalized);
    }

    public function rules(): array
    {
        $length = EmailVerificationService::OTP_LENGTH;

        return [
            'email' => ['required', 'email', 'max:255'],
            'otp'   => ['required', 'string', 'digits:'.$length],
        ];
    }

    public function messages(): array
    {
        return [
            'otp.digits' => 'Enter all '.EmailVerificationService::OTP_LENGTH.' digits of the code.',
        ];
    }
}
