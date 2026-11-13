<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/** New recovery codes for an admin's two-factor sign-in: their password first. */
class NewAdminRecoveryCodesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'password' => ['required', 'string'],
        ];
    }
}
