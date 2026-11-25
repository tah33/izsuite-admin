<?php

namespace App\Http\Requests\Admin;

use App\Models\Admin\Role;
use App\Support\AdminPassword;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateStaffRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = (int) $this->route('id');

        return [
            'first_name'    => ['required', 'string', 'max:255'],
            'last_name'     => ['nullable', 'string', 'max:255'],
            'email'         => ['required', 'email', 'unique:users,email,'.$id],
            'password'      => ['nullable', 'string', 'confirmed', AdminPassword::rule()],
            // A staff role only: never a client's, or another non-staff account's.
            'role_id'       => ['required', Rule::exists('roles', 'id')->whereNotIn('slug', Role::FRONTEND_SLUGS)],
            // No SVG: it can carry script, and it would be served from this site.
            'avatar'        => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            // The file picker's Remove button: no image, the first letter of the name instead.
            'remove_avatar' => ['nullable', 'boolean'],
        ];
    }

    public function attributes(): array
    {
        return ['avatar' => 'profile image'];
    }
}
