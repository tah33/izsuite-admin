<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * The package access matrix - app id => its ticked package ids - with the
 * admin's password again and a reason: saving it changes every plan's apps,
 * a critical action (Doc 15 section 15.25). CriticalActionService checks them.
 */
class SavePackageAccessRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'access'           => ['nullable', 'array'],
            'access.*'         => ['array'],
            'confirm_password' => ['nullable', 'string', 'max:255'],
            'reason'           => ['nullable', 'string', 'max:500'],
        ];
    }
}
