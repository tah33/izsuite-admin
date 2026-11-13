<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Resetting a staff member's two-factor sign-in: the Super Admin's password
 * again and a reason, as for any critical action (Doc 15 section 15.25).
 */
class ResetStaffTwoFactorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'confirm_password' => ['nullable', 'string', 'max:255'],
            'reason'           => ['nullable', 'string', 'max:500'],
        ];
    }
}
