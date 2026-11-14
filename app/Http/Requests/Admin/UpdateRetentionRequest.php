<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/** The days a privacy request's export lasts (Doc 15 section 15.24: "sensitive exported files expire after a configured period"). */
class UpdateRetentionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'privacy_export_days' => ['required', 'integer', 'between:1,90'],
        ];
    }

    public function messages(): array
    {
        return [
            'privacy_export_days.between' => 'Choose between 1 and 90 days.',
        ];
    }
}
