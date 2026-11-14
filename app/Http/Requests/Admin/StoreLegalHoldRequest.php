<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/** A legal hold on a business, by its BW- ID, with the matter's reference and why (Doc 15 Phase G2). */
class StoreLegalHoldRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'business'  => ['required', 'string', 'max:20', 'regex:/^\s*(BW-?)?\d+\s*$/i'],
            'reference' => ['nullable', 'string', 'max:100'],
            'reason'    => ['required', 'string', 'min:5', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'business.regex' => 'Enter the business by its ID, such as BW-000012.',
            'reason.min'     => 'Say why the business\'s records must be kept.',
        ];
    }
}
