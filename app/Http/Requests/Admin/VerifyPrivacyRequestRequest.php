<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/** How the person asking was checked to be who they say (Doc 15 section 15.24: "privacy requests require identity verification"). */
class VerifyPrivacyRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'identity_check' => ['required', 'string', 'min:5', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'identity_check.required' => 'Say how you checked that the person asking is who they say.',
            'identity_check.min'      => 'Say how you checked that the person asking is who they say.',
        ];
    }
}
