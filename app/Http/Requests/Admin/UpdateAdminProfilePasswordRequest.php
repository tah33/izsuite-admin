<?php

namespace App\Http\Requests\Admin;

use App\Support\AdminPassword;
use Illuminate\Foundation\Http\FormRequest;

class UpdateAdminProfilePasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'current_password' => ['required', 'current_password'],
            'password'         => ['required', 'confirmed', AdminPassword::rule()],
        ];
    }
}
