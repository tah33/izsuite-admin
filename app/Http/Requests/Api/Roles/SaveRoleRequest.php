<?php

namespace App\Http\Requests\Api\Roles;

use App\Support\Permissions;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * A role, added or changed (Doc 12 section 10): a name used once in the
     * business, a description, and what it allows - every permission there
     * is with full_access, else the permissions listed. What they need comes
     * with them (RoleService).
     */
    public function rules(): array
    {
        $workspace = $this->attributes->get('workspace');

        return [
            'name'          => ['required', 'string', 'max:100', Rule::unique('workspace_roles', 'name')->where('workspace_id', $workspace?->id)->ignore($this->route('id'))],
            'description'   => ['nullable', 'string', 'max:255'],
            'full_access'   => ['sometimes', 'boolean'],
            'permissions'   => ['present', 'array'],
            'permissions.*' => ['string', Rule::in(Permissions::keys())],
        ];
    }

    public function messages(): array
    {
        return [
            'name.unique'      => 'The business has a role of this name already.',
            'permissions.*.in' => 'That is not a permission a role can hold.',
        ];
    }
}
