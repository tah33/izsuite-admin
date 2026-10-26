<?php

namespace App\Http\Requests\Api\Imports;

use Illuminate\Foundation\Http\FormRequest;

class RunImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** The lines of the possible duplicates to add anyway; the rest of them are skipped. */
    public function rules(): array
    {
        return [
            'include'   => ['sometimes', 'array', 'max:5000'],
            'include.*' => ['integer', 'min:2'],
        ];
    }
}
