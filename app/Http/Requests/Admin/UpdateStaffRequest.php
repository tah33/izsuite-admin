<?php

namespace App\Http\Requests\Admin;

use App\Models\Admin\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

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
            'first_name' => ['required', 'string', 'max:255'],
            'last_name'  => ['nullable', 'string', 'max:255'],
            'email'      => ['required', 'email', 'unique:users,email,'.$id],
            'password'   => ['nullable', 'string', 'confirmed', Password::defaults()],
            // A staff role only: never a client's, or another non-staff account's.
            'role_id'    => ['required', Rule::exists('roles', 'id')->whereNotIn('slug', Role::FRONTEND_SLUGS)],
        ];
    }
}
