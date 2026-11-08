<?php

namespace App\Http\Requests\Api\Profile;

use App\Models\Admin\Language;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * My Profile's details (Doc 12 section 22). The name rules are the admin
     * profile form's - see UpdateAdminProfileRequest - and the phone,
     * language and time zone rules a business's user form's
     * (UpdateMemberRequest): empty language and time zone mean each
     * business's own. A field left out stays as it is.
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
            'phone'      => ['nullable', 'string', 'max:30'],
            'language'   => ['nullable', 'string', Rule::exists(Language::class, 'code')->where('is_active', true)],
            'timezone'   => ['nullable', 'string', 'timezone:all_with_bc'],
            'email'      => ['prohibited'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.prohibited'  => 'Your email address cannot be changed from the profile.',
            'language.exists'   => 'Pick one of the languages offered.',
            'timezone.timezone' => 'Pick one of the time zones offered.',
        ];
    }
}
