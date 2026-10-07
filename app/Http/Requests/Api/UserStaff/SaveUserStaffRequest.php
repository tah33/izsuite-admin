<?php

namespace App\Http\Requests\Api\UserStaff;

use App\Models\User\UserStaff;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveUserStaffRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * The same for adding and for editing, at the lengths the user_staff
     * columns hold.
     *
     * The name, email, phone and status are required - on an update too, so an
     * edit sends all four. The address, state and country are optional: null (or
     * a blank, which becomes null) clears one, and leaving the key out of an
     * update leaves it as it was.
     *
     * status is 1 (active) or 0 (inactive), and is never empty: a staff member
     * is always one or the other.
     *
     * user_id is not accepted at all - a staff member is always the caller's,
     * so one in the body is ignored.
     *
     * Surrounding whitespace is trimmed before this runs, so a name of nothing
     * but spaces is empty here and fails `required`.
     */
    public function rules(): array
    {
        return [
            'name'    => ['required', 'string', 'max:255'],
            'email'   => ['required', 'email', 'max:255'],
            'phone'   => ['required', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:255'],
            'state'   => ['nullable', 'string', 'max:100'],
            'country' => ['nullable', 'string', 'max:100'],
            'status'  => ['required', 'integer', Rule::in(UserStaff::STATUSES)],
        ];
    }

    public function messages(): array
    {
        return [
            'status.required' => 'Status must be 1 (active) or 0 (inactive).',
            'status.integer'  => 'Status must be 1 (active) or 0 (inactive).',
            'status.in'       => 'Status must be 1 (active) or 0 (inactive).',
        ];
    }
}
