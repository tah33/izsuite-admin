<?php

namespace App\Http\Requests\Api\Imports;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListImportRowsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Which rows: all of them; as checked - ready, possible duplicates, with
     * errors; or as done - created, skipped, failed.
     */
    public function rules(): array
    {
        return [
            'show'     => ['nullable', Rule::in(['all', 'ready', 'duplicates', 'errors', 'created', 'skipped', 'failed'])],
            'per_page' => ['nullable', 'integer', 'in:10,15,25,50,100'],
            'page'     => ['nullable', 'integer', 'min:1'],
        ];
    }
}
