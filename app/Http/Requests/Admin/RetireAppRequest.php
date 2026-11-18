<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/** Retiring an app (Doc 15 Phase E2): a critical action - the admin's password again, and the reason. */
class RetireAppRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'confirm_password' => ['nullable', 'string'],
            'reason'           => ['nullable', 'string', 'max:500'],
        ];
    }
}
