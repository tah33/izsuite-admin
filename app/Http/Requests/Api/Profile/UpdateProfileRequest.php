<?php

namespace App\Http\Requests\Api\Profile;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * The same name rules as the admin profile form - see
     * UpdateAdminProfileRequest.
     *
     * Email is the one field that form edits and this one does not. It is the
     * login, and sign-up proved the address by code; changing it here without
     * proving the new one would quietly undo that. `prohibited` answers with a
     * 422 that says so, rather than accepting the field and ignoring it.
     */
    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:255'],
            'last_name'  => ['nullable', 'string', 'max:255'],
            'email'      => ['prohibited'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.prohibited' => 'Your email address cannot be changed from the profile.',
        ];
    }
}
