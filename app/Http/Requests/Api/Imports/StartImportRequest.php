<?php

namespace App\Http\Requests\Api\Imports;

use App\Models\User\Import;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StartImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** A CSV file of contacts or items, up to 10 MB - about 5,000 rows of the most a row may hold. */
    public function rules(): array
    {
        return [
            'kind' => ['required', Rule::in(Import::KINDS)],
            'file' => ['required', 'file', 'max:10240', 'mimes:csv,txt'],
        ];
    }

    public function messages(): array
    {
        return [
            'kind.in'       => 'Import contacts or items.',
            'file.required' => 'Choose the CSV file to import.',
            'file.mimes'    => 'Import a CSV file. In Excel, use Save As and choose CSV (UTF-8).',
            'file.max'      => 'The file can be up to 10 MB. Split it and import each part.',
            'file.uploaded' => 'The file could not be uploaded. It can be up to 10 MB.',
        ];
    }
}
