<?php

namespace App\Http\Requests\Api\Members;

use App\Models\Admin\Language;
use App\Models\User\WorkspaceMember;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * A user of the business, changed (Doc 12 sections 6 to 8 and 20): who
     * they are, their role, and the locations they may use - all, or those
     * listed - with a default. Which role and locations the business has is
     * MemberService's to check.
     */
    public function rules(): array
    {
        return [
            'name'                => ['required', 'string', 'max:150'],
            'role_id'             => ['required', 'integer'],
            'location_access'     => ['required', Rule::in([WorkspaceMember::ALL_LOCATIONS, WorkspaceMember::SELECTED_LOCATIONS])],
            'location_ids'        => ['nullable', 'array', 'required_if:location_access,'.WorkspaceMember::SELECTED_LOCATIONS],
            'location_ids.*'      => ['integer'],
            'default_location_id' => ['nullable', 'integer'],
            'phone'               => ['nullable', 'string', 'max:30'],
            'job_title'           => ['nullable', 'string', 'max:100'],
            'language'            => ['nullable', 'string', Rule::exists(Language::class, 'code')->where('is_active', true)],
            'timezone'            => ['nullable', 'string', 'timezone:all_with_bc'],
        ];
    }

    public function messages(): array
    {
        return [
            'location_ids.required_if' => 'Choose the locations this user may use.',
        ];
    }
}
