<?php

namespace App\Http\Requests\Api\UserStaff;

use App\Models\Frontend\Application;
use App\Models\User\UserStaff;
use App\Models\User\Workspace;
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
     * assigned_apps and assigned_workspaces are lists of ids - any number of each,
     * none at all if the list is empty or null; left out of an update, they are
     * left as they were. Each id must be on offer: an app an admin has left active
     * (the list GET /apps hands the form), or one of the caller's own workspaces
     * (GET /workspaces) - never another account's.
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

            'assigned_apps'   => ['nullable', 'array'],
            'assigned_apps.*' => ['integer', 'distinct', Rule::exists(Application::class, 'id')->where('is_active', true)],

            'assigned_workspaces'   => ['nullable', 'array'],
            'assigned_workspaces.*' => ['integer', 'distinct', Rule::exists(Workspace::class, 'id')->where('user_id', $this->user()->id)],
        ];
    }

    public function attributes(): array
    {
        return [
            'assigned_apps'         => 'assigned apps',
            'assigned_workspaces'   => 'assigned workspaces',
        ];
    }

    public function messages(): array
    {
        return [
            'status.required' => 'Status must be 1 (active) or 0 (inactive).',
            'status.integer'  => 'Status must be 1 (active) or 0 (inactive).',
            'status.in'       => 'Status must be 1 (active) or 0 (inactive).',

            'assigned_apps.array'            => 'Choose the apps from the list.',
            'assigned_apps.*.integer'        => 'Choose the apps from the list.',
            'assigned_apps.*.distinct'       => 'An app can be chosen only once.',
            'assigned_apps.*.exists'         => 'Choose only from the available apps.',

            'assigned_workspaces.array'      => 'Choose the workspaces from the list.',
            'assigned_workspaces.*.integer'  => 'Choose the workspaces from the list.',
            'assigned_workspaces.*.distinct' => 'A workspace can be chosen only once.',
            'assigned_workspaces.*.exists'   => 'Choose only from your own workspaces.',
        ];
    }
}
