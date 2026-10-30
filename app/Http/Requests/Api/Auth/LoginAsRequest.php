<?php

namespace App\Http\Requests\Api\Auth;

use App\Http\Requests\Api\Auth\Concerns\RejectsCredentialsInQueryString;
use Illuminate\Foundation\Http\FormRequest;

/** The one-time code from the admin panel's "Login as" link - in the body only. */
class LoginAsRequest extends FormRequest
{
    use RejectsCredentialsInQueryString;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->rejectCredentialsInQueryString(['code']);
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:255'],
        ];
    }
}
