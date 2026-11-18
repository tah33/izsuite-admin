<?php

namespace App\Http\Requests\Api\Profile;

use Illuminate\Foundation\Http\FormRequest;

/**
 * The user's password, which setting up two-factor authentication, turning it
 * off and making new recovery codes each ask for again (Doc 12 section 22).
 */
class TwoFactorPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'password' => ['required', 'string'],
        ];
    }
}
