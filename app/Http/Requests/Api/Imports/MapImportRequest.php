<?php

namespace App\Http\Requests\Api\Imports;

use Illuminate\Foundation\Http\FormRequest;

class MapImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Which column fills which field: field key => the column's number,
     * counting from 0, or null for none. The rest - every required field
     * matched, a column used once - is ImportService's to check.
     */
    public function rules(): array
    {
        return [
            'mapping'   => ['required', 'array'],
            'mapping.*' => ['nullable', 'integer', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'mapping.required' => 'Match the file\'s columns to fields.',
            'mapping.*.integer' => 'Choose one of the file\'s columns.',
            'mapping.*.min'     => 'Choose one of the file\'s columns.',
        ];
    }
}
