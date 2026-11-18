<?php

namespace App\Http\Requests\Api\Search;

use Illuminate\Foundation\Http\FormRequest;

class GlobalSearchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** What the header's search box sends (Doc 14 section 16): two characters at least. */
    public function rules(): array
    {
        return [
            'q' => ['required', 'string', 'min:2', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'q.required' => 'Type what to search for.',
            'q.min'      => 'Type at least 2 characters to search.',
        ];
    }

    /** Spaces around the term are not part of it. */
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('q'))) {
            $this->merge(['q' => trim($this->input('q'))]);
        }
    }
}
