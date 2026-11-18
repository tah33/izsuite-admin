<?php

namespace App\Http\Requests\Admin;

use App\Models\Admin\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StoreRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** A role is known by the slug of its name (RoleService::create), which must be free. */
    protected function prepareForValidation(): void
    {
        $this->merge(['slug' => Str::slug((string) $this->input('name'))]);
    }

    public function rules(): array
    {
        return [
            'name'          => ['required', 'string', 'max:255', 'unique:roles,name'],
            // Never a website account's role, nor the Super Admin's: a role of that name would be one.
            'slug'          => ['required', Rule::notIn([...Role::FRONTEND_SLUGS, 'super-admin']), Rule::unique('roles', 'slug')],
            'permissions'   => ['nullable', 'array'],
            'permissions.*' => ['string'],
        ];
    }

    /** The form shows a name's errors under Name, where a taken slug is the name's fault. */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($validator->errors()->has('slug') && ! $validator->errors()->has('name')) {
                $validator->errors()->add('name', $validator->errors()->first('slug'));
            }
        });
    }

    public function messages(): array
    {
        return [
            'slug.required' => 'Give the role a name with letters or numbers in it.',
            'slug.not_in'   => 'That name belongs to another kind of account. Choose another.',
            'slug.unique'   => 'A role with this name exists already. Choose another.',
        ];
    }
}
