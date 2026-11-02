<?php

namespace App\Http\Requests\Api\Members;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class RegisterFromInvitationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * The account an invitation makes (Doc 12 section 6): a name and a
     * password - the e-mail is the invitation's - and the terms accepted, as
     * at any sign-up.
     */
    public function rules(): array
    {
        return [
            'first_name'   => ['required', 'string', 'max:255'],
            'last_name'    => ['nullable', 'string', 'max:255'],
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
}
